<?php

namespace Tests\Feature\Research;

use Mockery;
use Tests\TestCase;
use App\Models\Brand;
use RuntimeException;
use App\Models\ResearchRun;
use App\Helpers\ApifyHelper;
use Psr\Log\LoggerInterface;
use App\Services\UserService;
use App\Services\BrandService;
use App\Exceptions\ApiException;
use App\Models\KnowledgeInsight;
use Illuminate\Support\Facades\DB;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Log;
use App\Services\ResearchRunService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Website\CheckWebsiteScrapingJob;
use App\Jobs\Research\Website\StartWebsiteScrapingJob;
use App\Jobs\Research\Website\AnalyzeWebsiteContentJob;
use App\Services\Dispatchers\ResearchDispatcherService;


class WebsiteResearchTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config()->set('services.apify.api_key', 'testing-key');
        foreach ([StartWebsiteScrapingJob::class, CheckWebsiteScrapingJob::class,
            AnalyzeWebsiteContentJob::class] as $jobClass) {
            foreach (['Info', 'Errors'] as $suffix) {
                config()->set('logging.channels.'.class_basename($jobClass).$suffix, config('logging.channels.null'));
            }
        }

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, [
            'name' => 'Mi marca',
            'website_url' => 'https://example.com',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // La URL se toma de la marca autenticada y queda congelada; no se aceptan referencias ajenas.
    #[Test]
    public function starts_with_saved_url_and_rejects_a_second_active_run(): void
    {
        $response = $this->postJson('/api/research-runs', [
            'type' => 'website', 'brand_id' => 999, 'input' => ['url' => 'https://other.example'],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.brand_id', $this->brand->id)
            ->assertJsonPath('data.input.url', 'https://example.com');
        $id = $response->json('data.id');
        resolve(BrandService::class)->update($this->brand, ['website_url' => 'https://changed.example']);

        $this->postJson('/api/research-runs', ['type' => 'website'])
            ->assertConflict()->assertJsonPath('code', 'research_already_running');
        $this->getJson("/api/research-runs/{$id}")->assertOk()
            ->assertJsonPath('data.input.url', 'https://example.com');
        Queue::assertPushedOn('scraping_queue', StartWebsiteScrapingJob::class);
        Queue::assertPushed(StartWebsiteScrapingJob::class, 1);
        $this->assertDatabaseCount('research_runs', 1);
    }


    // El recorrido guarda contenido real del proveedor y hallazgos inequívocamente simulados.
    #[Test]
    public function completes_the_pipeline_without_duplicate_scrapes_or_insights(): void
    {
        $run = $this->createRun();
        $this->fakeSuccessfulScraping();

        (new StartWebsiteScrapingJob($run->id))->handle();
        (new StartWebsiteScrapingJob($run->id))->handle();
        $this->assertSame('scraping', $run->fresh()->status);
        Queue::assertPushed(CheckWebsiteScrapingJob::class, function (CheckWebsiteScrapingJob $job): bool {
            return $job->queue === 'scraping_queue' && $job->delay->isFuture();
        });

        (new CheckWebsiteScrapingJob($run->id))->handle();
        (new CheckWebsiteScrapingJob($run->id))->handle();
        $this->assertSame('analyzing', $run->fresh()->status);
        Queue::assertPushedOn('analysis_queue', AnalyzeWebsiteContentJob::class);
        Queue::assertPushed(AnalyzeWebsiteContentJob::class, 1);

        (new AnalyzeWebsiteContentJob($run->id))->handle();
        (new AnalyzeWebsiteContentJob($run->id))->handle();
        $this->getJson("/api/research-runs/{$run->id}")->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonCount(2, 'data.knowledge_sources')
            ->assertJsonCount(2, 'data.knowledge_insights')
            ->assertJsonPath('data.knowledge_insights.0.model', 'mock')
            ->assertJsonPath('data.knowledge_insights.0.payload.is_mock', true);
        $this->assertDatabaseCount('knowledge_sources', 2);
        $this->assertDatabaseCount('knowledge_insights', 2);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request['startUrls'] === [['url' => 'https://example.com']]
            && $request['maxCrawlPages'] === 10);
    }


    // Mientras Apify trabaja se agenda otra comprobación, sin ejecutar todavía la etapa de IA.
    #[Test]
    public function reschedules_running_scraping_and_expires_overdue_runs(): void
    {
        $run = $this->createRun();
        $run->update(['status' => 'scraping', 'external_run_id' => 'run123', 'started_at' => now()]);
        Http::fake(['*/actor-runs/run123' => Http::response(['data' => [
            'id' => 'run123', 'status' => 'RUNNING', 'defaultDatasetId' => 'dataset123',
        ]])]);

        (new CheckWebsiteScrapingJob($run->id))->handle();
        $this->assertSame('scraping', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->last_checked_at);
        Queue::assertPushed(CheckWebsiteScrapingJob::class, 1);
        Queue::assertNotPushed(AnalyzeWebsiteContentJob::class);

        $this->travel(11)->minutes();
        (new CheckWebsiteScrapingJob($run->id))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->finished_at);
        Queue::assertPushed(CheckWebsiteScrapingJob::class, 1);
    }


    // Los estados terminales de Apify y un dataset vacío finalizan la ejecución como fallida.
    #[Test]
    #[DataProvider('unsuccessfulScrapingStates')]
    public function records_unsuccessful_scraping(string $status): void
    {
        $run = $this->createRun();
        $run->update(['status' => 'scraping', 'external_run_id' => 'run123', 'started_at' => now()]);
        Http::fake([
            '*/actor-runs/run123' => Http::response(['data' => [
                'id' => 'run123', 'status' => $status, 'defaultDatasetId' => 'dataset123',
            ]]),
            '*/datasets/dataset123/items*' => Http::response([]),
        ]);

        (new CheckWebsiteScrapingJob($run->id))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        Queue::assertNotPushed(AnalyzeWebsiteContentJob::class);
    }


    public static function unsuccessfulScrapingStates(): array
    {
        return [['FAILED'], ['ABORTED'], ['TIMED-OUT'], ['SUCCEEDED']];
    }


    // Una nueva investigación reutiliza fuentes idénticas y mantiene las correcciones anteriores.
    #[Test]
    public function reuses_sources_and_preserves_manual_edits_and_previous_results(): void
    {
        $this->fakeSuccessfulScraping();
        $first = $this->createRun();
        (new StartWebsiteScrapingJob($first->id))->handle();
        (new CheckWebsiteScrapingJob($first->id))->handle();
        (new AnalyzeWebsiteContentJob($first->id))->handle();
        $insight = KnowledgeInsight::query()->firstOrFail();
        $insight->update(['user_body' => 'Corrección del usuario', 'is_user_edited' => true]);

        $second = $this->createRun();
        $this->getJson('/api/research-runs/website/status')->assertOk()
            ->assertJsonPath('data.active.id', $second->id)
            ->assertJsonPath('data.last_completed.id', $first->id);
        (new StartWebsiteScrapingJob($second->id))->handle();
        (new CheckWebsiteScrapingJob($second->id))->handle();
        (new AnalyzeWebsiteContentJob($second->id))->handle();

        $this->assertDatabaseCount('knowledge_sources', 2);
        $this->assertSame($first->fresh()->knowledge_source_ids, $second->fresh()->knowledge_source_ids);
        $this->assertSame('Corrección del usuario', $insight->fresh()->getEffectiveBody());
        $this->assertTrue($insight->fresh()->is_user_edited);
        $this->getJson('/api/research-runs/website/status')->assertOk()
            ->assertJsonPath('data.active', null)
            ->assertJsonPath('data.last_completed.id', $second->id);
    }


    // El fallo definitivo cambia el estado visible, pero un fallo tardío no pisa una etapa posterior.
    #[Test]
    public function marks_permanent_failures_without_overwriting_later_stages(): void
    {
        $run = $this->createRun();
        $job = unserialize(serialize(new StartWebsiteScrapingJob($run->id)));
        $job->failed(new RuntimeException('secret-data'));
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertStringNotContainsString('secret-data', $run->fresh()->error_message);
        $this->getJson('/api/research-runs/website/status')->assertOk()
            ->assertJsonPath('data.latest.status', 'failed')
            ->assertJsonPath('data.active', null);

        $run->update(['status' => 'completed']);
        (new CheckWebsiteScrapingJob($run->id))->failed(new RuntimeException('late failure'));
        $this->assertSame('completed', $run->fresh()->status);
    }


    // Un inicio con respuesta perdida nunca vuelve a llamar al proveedor al recibir el mismo job.
    #[Test]
    public function does_not_start_apify_again_after_an_ambiguous_failure(): void
    {
        $run = $this->createRun();
        $helper = Mockery::mock(ApifyHelper::class);
        $helper->shouldReceive('startWebsiteContentCrawler')->once()->andThrow(new RuntimeException('Connection lost'));
        $this->app->instance(ApifyHelper::class, $helper);
        $job = new StartWebsiteScrapingJob($run->id);

        try {
            $job->handle();
            $this->fail('El error del proveedor debía propagarse.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }
        $job->handle();
        $this->assertSame('failed', $run->fresh()->status);
        Queue::assertNotPushed(CheckWebsiteScrapingJob::class);
    }


    // Un error al encolar revierte también la creación, evitando una ejecución pendiente sin job.
    #[Test]
    public function rolls_back_creation_when_dispatch_fails(): void
    {
        $dispatcher = Mockery::mock(ResearchDispatcherService::class);
        $dispatcher->shouldReceive('dispatchStartWebsiteScrapingJob')->once()
            ->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(ResearchDispatcherService::class, $dispatcher);

        $this->postJson('/api/research-runs', ['type' => 'website'])->assertStatus(500);
        $this->assertDatabaseCount('research_runs', 0);
    }


    // Las lecturas y la creación requieren autenticación y nunca exponen ejecuciones de otra marca.
    #[Test]
    public function isolates_runs_and_validates_the_requested_source(): void
    {
        $run = $this->createRun();
        $otherUser = UserFactory::new()->owner()->create();
        resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($otherUser);
        $this->withToken($credentials['token']);

        $this->getJson("/api/research-runs/{$run->id}")->assertNotFound();
        $this->getJson('/api/research-runs/website/status')->assertOk()->assertJsonPath('data.latest', null);
        $this->postJson('/api/research-runs', ['type' => 'website'])->assertUnprocessable()
            ->assertJsonValidationErrors('website_url');
        $this->postJson('/api/research-runs', ['type' => 'instagram'])->assertUnprocessable()
            ->assertJsonValidationErrors('type');
        $this->withoutToken();
        $this->getJson("/api/research-runs/{$run->id}")->assertUnauthorized();
        $this->postJson('/api/research-runs', ['type' => 'website'])->assertUnauthorized();
    }


    // La ejecución y su job se escriben en la misma transacción de la conexión database.
    #[Test]
    public function rolls_back_database_queue_payload_with_its_research_run(): void
    {
        $this->app->forgetInstance('queue');
        Queue::clearResolvedInstance('queue');
        config()->set('queue.default', 'database');
        DB::beginTransaction();
        $run = $this->createRun();
        $queued = DB::table('jobs')->where('queue', 'scraping_queue')->first();
        $this->assertNotNull($queued);
        $this->assertStringNotContainsString('https://example.com', $queued->payload);
        $this->assertStringContainsString('researchRunId', $queued->payload);
        DB::rollBack();

        $this->assertDatabaseMissing('research_runs', ['id' => $run->id]);
        $this->assertDatabaseCount('jobs', 0);
    }


    // Un dataset inválido no deja fuentes parciales; el fallo definitivo queda visible.
    #[Test]
    public function rejects_invalid_external_content_without_partial_sources(): void
    {
        $run = $this->createRun();
        $run->update(['status' => 'scraping', 'external_run_id' => 'run123', 'started_at' => now()]);
        Http::fake([
            '*/actor-runs/run123' => Http::response(['data' => [
                'id' => 'run123', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'dataset123',
            ]]),
            '*/datasets/dataset123/items*' => Http::response([
                ['url' => 'https://example.com', 'text' => 'Contenido válido'],
                ['url' => 'https://example.com/about', 'text' => ['invalid']],
            ]),
        ]);
        $job = new CheckWebsiteScrapingJob($run->id);
        try {
            $job->handle();
            $this->fail('Se esperaba rechazar la respuesta inválida.');
        } catch (ApiException $exception) {
            $this->assertSame('apify_content_invalid', $exception->errorCode);
            $this->assertSame('scraping', $run->fresh()->status);
            $job->failed($exception);
        }

        $this->assertSame('failed', $run->fresh()->status);
        $this->assertDatabaseCount('knowledge_sources', 0);
        Queue::assertNotPushed(AnalyzeWebsiteContentJob::class);
    }


    // Los IDs conservados en un JSON también se filtran por marca al recuperar las fuentes.
    #[Test]
    public function filters_foreign_source_ids_from_results(): void
    {
        $otherUser = UserFactory::new()->owner()->create();
        $otherBrand = resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $source = resolve(KnowledgeSourceService::class)->create($otherBrand, [
            'type' => 'web_page', 'title' => 'Privado', 'status' => 'ready',
        ]);
        $run = $this->createRun();
        $run->update(['knowledge_source_ids' => [$source->id]]);

        $this->getJson("/api/research-runs/{$run->id}")->assertOk()->assertJsonCount(0, 'data.knowledge_sources');
    }


    // La serialización conserva el UUID incluso cuando failed() recibe otra instancia del job.
    #[Test]
    public function correlates_logs_across_serialization_and_records_skip_reasons(): void
    {
        $run = $this->createRun();
        $run->update(['status' => 'scraping']);
        $job = new StartWebsiteScrapingJob($run->id);
        $payload = serialize($job);
        $messages = [];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->twice()
            ->withArgs(function (string $message, array $context) use (&$messages): bool {
                $messages[] = $message;
                return isset($context['researchRunId']);
            });
        $logger->shouldReceive('error')->once()
            ->withArgs(function (string $message, array $context) use (&$messages): bool {
                $messages[] = $message;
                return isset($context['exception']);
            });
        Log::shouldReceive('channel')
            ->with('StartWebsiteScrapingJobInfo')->andReturn($logger);
        Log::shouldReceive('channel')
            ->with('StartWebsiteScrapingJobErrors')->andReturn($logger);

        $job->handle();
        unserialize($payload)->failed(new RuntimeException('Failure'));

        $this->assertStringContainsString('no longer pending', $messages[1]);
        preg_match('/^\[([a-f0-9-]{36})\] \| /', $messages[0], $matches);
        $this->assertNotEmpty($matches);
        foreach ($messages as $message) {
            $this->assertStringStartsWith($matches[0], $message);
        }
    }


    // Si falla el despacho de la siguiente etapa, se revierten las fuentes y el avance juntos.
    #[Test]
    public function rolls_back_sources_when_analysis_dispatch_fails(): void
    {
        $run = $this->createRun();
        $run->update(['status' => 'scraping', 'external_run_id' => 'run123', 'started_at' => now()]);
        $this->fakeSuccessfulScraping();
        $dispatcher = Mockery::mock(ResearchDispatcherService::class);
        $dispatcher->shouldReceive('dispatchAnalyzeWebsiteContentJob')->once()
            ->andThrow(new RuntimeException('Queue unavailable'));
        $this->app->instance(ResearchDispatcherService::class, $dispatcher);

        try {
            (new CheckWebsiteScrapingJob($run->id))->handle();
            $this->fail('Se esperaba el error del despacho.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('knowledge_sources', 0);
        $this->assertSame('scraping', $run->fresh()->status);
        $this->assertSame([], $run->fresh()->knowledge_source_ids);
    }


    // Una ejecución concurrente se omite en el job, antes de acceder al proveedor o guardar resultados.
    #[Test]
    public function skips_jobs_already_running_and_releases_lock_after_failure(): void
    {
        $run = $this->createRun();
        Http::fake();
        foreach ([StartWebsiteScrapingJob::class, CheckWebsiteScrapingJob::class,
            AnalyzeWebsiteContentJob::class] as $jobClass) {
            $key = $jobClass.':'.$run->id;
            $lock = Cache::lock($key, 90);
            $this->assertTrue($lock->get());
            try {
                (new $jobClass($run->id))->handle();
                $this->assertSame('pending', $run->fresh()->status);
                Queue::assertPushed($jobClass, fn (object $job): bool => $job->delay?->isFuture() === true);
            } finally {
                $lock->release();
            }
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('knowledge_insights', 0);

        $helper = Mockery::mock(ApifyHelper::class);
        $helper->shouldReceive('startWebsiteContentCrawler')->once()->andThrow(new RuntimeException('Failed'));
        $this->app->instance(ApifyHelper::class, $helper);
        try {
            (new StartWebsiteScrapingJob($run->id))->handle();
            $this->fail('Se esperaba el fallo del proveedor.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Failed', $exception->getMessage());
        }
        $lock = Cache::lock(StartWebsiteScrapingJob::class.':'.$run->id, 90);
        $this->assertTrue($lock->get());
        $lock->release();
    }


    private function createRun(): ResearchRun
    {
        return resolve(ResearchRunService::class)->create($this->brand, ['type' => 'website']);
    }


    private function fakeSuccessfulScraping(): void
    {
        Http::fake([
            '*/actors/apify~website-content-crawler/runs' => Http::response(['data' => [
                'id' => 'run123', 'status' => 'RUNNING', 'defaultDatasetId' => 'dataset123',
            ]]),
            '*/actor-runs/run123' => Http::response(['data' => [
                'id' => 'run123', 'status' => 'SUCCEEDED', 'defaultDatasetId' => 'dataset123',
            ]]),
            '*/datasets/dataset123/items*' => Http::response([
                ['url' => 'https://example.com', 'metadata' => ['title' => 'Inicio'], 'text' => 'Servicios de ejemplo'],
                ['url' => 'https://example.com/about', 'markdown' => '# Sobre nosotros'],
            ]),
        ]);
    }

}

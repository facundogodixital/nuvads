<?php

namespace Tests\Feature\Competitors;

use Tests\TestCase;
use App\Models\Competitor;
use App\Services\UserService;
use Illuminate\Support\Sleep;
use App\Services\BrandService;
use App\Services\CompetitorService;
use Database\Factories\UserFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use App\Models\CompetitorResearchRun;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use App\Services\CompetitorResearchRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;
use App\Jobs\Research\Competitors\ResearchCompetitorInstagramJob;


class CompetitorInstagramResearchTest extends TestCase
{

    use RefreshDatabase;

    private Competitor $competitor;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Sleep::fake();
        config()->set('services.apify.api_key', 'testing-key');
        config()->set('services.openai.api_key', 'testing-key');
        config()->set('logging.channels.ResearchCompetitorInstagramJobInfo', config('logging.channels.null'));
        config()->set('logging.channels.ResearchCompetitorInstagramJobErrors', config('logging.channels.null'));

        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $this->competitor = resolve(CompetitorService::class)->create($brand, [
            'name' => 'Vivero Sur',
            'instagram_username' => 'vivero.sur',
        ]);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Transcribe los posteos salteando el que falla, analiza con métricas que ignoran los likes ocultos, mezcla los
    // campos y encola el cruce; la pantalla lee el análisis, las conclusiones y los posteos.
    #[Test]
    public function analyzes_the_posts_and_merges_the_competitor_fields(): void
    {
        Http::fake([
            'https://api.apify.com/v2/actors/*' => Http::response($this->apifyRun('READY')),
            'https://api.apify.com/v2/actor-runs/run1' => Http::sequence()
                ->push($this->apifyRun('RUNNING'))
                ->push($this->apifyRun('SUCCEEDED')),
            'https://api.apify.com/v2/datasets/dataset1/items*' => Http::response($this->apifyPosts()),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->openAiResponse($this->transcription()))
                ->push(['error' => ['message' => 'Error while downloading the image.']], 400)
                ->push($this->openAiResponse($this->transcription()))
                ->push($this->openAiResponse($this->analysis())),
        ]);
        $researchRun = $this->createResearchRun();

        (new ResearchCompetitorInstagramJob($researchRun->id))->handle();

        $researchRun->refresh();
        $this->assertSame('completed', $researchRun->status);
        $this->assertCount(2, $researchRun->competitor_source_ids);
        // El análisis final va solo con texto: las imágenes ya se transcribieron.
        $analysisInput = $this->decodeOpenAiText($this->recordedOpenAiRequests()[3]['input']);
        $this->assertNull($analysisInput['metrics']['formats']['image']['average_likes']);
        $this->assertSame(300, $analysisInput['metrics']['formats']['reel']['average_likes']);
        $competitorStrengths = $this->competitor->fresh()->competitor_strengths_description;
        $this->assertSame('Sus reels tienen el doble de likes.', $competitorStrengths);
        Queue::assertPushedOn('research_queue', ResearchBrandCompetitionJob::class);

        $instagramInsights = $this->getJson("/api/competitors/{$this->competitor->id}/insights/instagram")
            ->assertOk()
            ->json('data');
        $this->assertSame(3, $instagramInsights['analysis']['payload']['metrics']['posts_count']);
        $this->assertCount(1, $instagramInsights['insights']);
        $this->assertSame($researchRun->competitor_source_ids, array_column($instagramInsights['posts'], 'id'));
    }


    private function createResearchRun(): CompetitorResearchRun
    {
        return resolve(CompetitorResearchRunService::class)->create($this->competitor, 'instagram');
    }


    private function recordedOpenAiRequests(): array
    {
        $isOpenAiRequest = fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/responses';

        return Http::recorded($isOpenAiRequest)->map(fn (array $pair): Request => $pair[0])->values()->all();
    }


    // El helper de OpenAI agrega un recordatorio de JSON al final del texto; se decodifica solo el objeto.
    private function decodeOpenAiText(string $text): array
    {
        $jsonObject = substr($text, 0, strrpos($text, '}') + 1);

        return json_decode($jsonObject, true);
    }


    private function apifyRun(string $status): array
    {
        return ['data' => ['id' => 'run1', 'status' => $status, 'defaultDatasetId' => 'dataset1']];
    }


    // Una imagen con los likes ocultos, un carrusel y un reel.
    private function apifyPosts(): array
    {
        return [
            [
                'type' => 'Image',
                'url' => 'https://www.instagram.com/p/image/',
                'caption' => 'Llegaron las suculentas',
                'displayUrl' => 'https://cdn.example/image.jpg',
                'images' => [],
                'likesCount' => -1,
                'commentsCount' => 4,
                'timestamp' => '2026-09-01T12:00:00.000Z',
                'isPinned' => false,
            ],
            [
                'type' => 'Sidecar',
                'url' => 'https://www.instagram.com/p/carousel/',
                'caption' => 'Cómo trasplantar',
                'displayUrl' => 'https://cdn.example/carousel-1.jpg',
                'images' => ['https://cdn.example/carousel-1.jpg', 'https://cdn.example/carousel-2.jpg'],
                'likesCount' => 120,
                'commentsCount' => 10,
                'timestamp' => '2026-09-08T12:00:00.000Z',
                'isPinned' => false,
            ],
            [
                'type' => 'Video',
                'url' => 'https://www.instagram.com/p/reel/',
                'caption' => null,
                'displayUrl' => 'https://cdn.example/reel.jpg',
                'images' => [],
                'likesCount' => 300,
                'commentsCount' => 20,
                'timestamp' => '2026-09-15T12:00:00.000Z',
                'isPinned' => false,
            ],
        ];
    }


    private function transcription(): array
    {
        return ['images' => [['transcription' => null, 'description' => 'Una planta en maceta.']]];
    }


    private function analysis(): array
    {
        return [
            'matches_competitor' => true,
            'competitor' => [
                'competitor_offer_description' => null,
                'competitor_customers_description' => null,
                'competitor_strengths_description' => 'Sus reels tienen el doble de likes.',
                'competitor_weaknesses_description' => null,
                'competitor_communication_description' => null,
                'competitor_differentiators_description' => null,
            ],
            'summary' => 'Publica tutoriales y novedades.',
            'insights' => ['Los reels le dan más interacción que los carruseles.'],
        ];
    }


    private function openAiResponse(array $content): array
    {
        return ['status' => 'completed', 'output' => [[
            'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => json_encode($content)]],
        ]]];
    }

}

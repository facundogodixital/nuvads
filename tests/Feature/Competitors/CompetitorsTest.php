<?php

namespace Tests\Feature\Competitors;

use Tests\TestCase;
use App\Models\Brand;
use App\Services\UserService;
use App\Services\BrandService;
use App\Services\CompetitorService;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;
use App\Jobs\Research\Competitors\ResearchCompetitorInstagramJob;


class CompetitorsTest extends TestCase
{

    use RefreshDatabase;

    private Brand $brand;


    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $user = UserFactory::new()->owner()->create();
        $this->brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);
        $this->withToken($credentials['token']);
    }


    // Se da de alta con los enlaces normalizados, se lista con el estado de sus fuentes y se edita; al borrarlo, la
    // marca vuelve a calcular lo que sabe de su competencia.
    #[Test]
    public function creates_lists_updates_and_deletes_a_competitor(): void
    {
        $competitorId = $this->postJson('/api/competitors', [
            'name' => 'Vivero Sur',
            'instagram_username' => 'https://www.instagram.com/Vivero.Sur/',
            'meta_ads_url' => 'm.facebook.com/viverosur?ref=1',
        ])->assertCreated()
            ->assertJsonPath('data.brand_id', $this->brand->id)
            ->assertJsonPath('data.instagram_username', 'vivero.sur')
            ->assertJsonPath('data.meta_ads_url', 'https://www.facebook.com/viverosur')
            ->json('data.id');

        $this->getJson('/api/competitors')->assertOk()
            ->assertJsonPath('data.competitors.0.id', $competitorId)
            ->assertJsonPath("data.research_statuses.{$competitorId}.instagram.latest", null);

        $this->patchJson("/api/competitors/{$competitorId}", [
            'competitor_strengths_description' => 'Sus reels de trasplantes.',
        ])->assertOk()->assertJsonPath('data.competitor_strengths_description', 'Sus reels de trasplantes.');

        $this->deleteJson("/api/competitors/{$competitorId}")->assertOk();

        $this->getJson('/api/competitors')->assertOk()->assertJsonPath('data.competitors', []);
        Queue::assertPushedOn('research_queue', ResearchBrandCompetitionJob::class);
    }


    // Los competidores de otro cliente no se ven ni se pueden tocar: el listado no los trae y cada ruta con su ID
    // responde 404.
    #[Test]
    public function hides_competitors_of_other_clients(): void
    {
        $otherUser = UserFactory::new()->owner()->create();
        $otherBrand = resolve(BrandService::class)->create($otherUser->client, ['name' => 'Otra marca']);
        $otherCompetitor = resolve(CompetitorService::class)->create($otherBrand, [
            'name' => 'Competidor ajeno',
            'instagram_username' => 'ajeno',
        ]);
        $competitorUrl = "/api/competitors/{$otherCompetitor->id}";

        $this->getJson('/api/competitors')->assertOk()->assertJsonPath('data.competitors', []);
        $this->getJson($competitorUrl)->assertNotFound();
        $this->patchJson($competitorUrl, ['name' => 'Cambiado'])->assertNotFound();
        $this->deleteJson($competitorUrl)->assertNotFound();
        $this->postJson("{$competitorUrl}/research-runs", ['type' => 'instagram'])->assertNotFound();
        $this->getJson("{$competitorUrl}/research-runs/instagram/status")->assertNotFound();
        $this->getJson("{$competitorUrl}/insights/instagram")->assertNotFound();

        $this->assertSame('Competidor ajeno', $otherCompetitor->fresh()->name);
        Queue::assertNothingPushed();
    }


    // Una fuente se analiza con el enlace guardado, que queda congelado en la investigación junto con la configuración
    // de competencia. Sin enlace no arranca, y solo puede haber una investigación activa por competidor y fuente.
    #[Test]
    public function starts_a_research_with_the_saved_link_and_one_active_run_per_source(): void
    {
        $competitor = resolve(CompetitorService::class)->create($this->brand, ['name' => 'Vivero Sur']);
        $researchRunsUrl = "/api/competitors/{$competitor->id}/research-runs";

        $this->postJson($researchRunsUrl, ['type' => 'instagram'])->assertUnprocessable()
            ->assertJsonPath('code', 'competitor_link_missing');

        resolve(CompetitorService::class)->update($competitor, ['instagram_username' => 'vivero.sur']);
        $this->postJson($researchRunsUrl, ['type' => 'instagram'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.input.username', 'vivero.sur')
            ->assertJsonPath('data.input.posts_limit', config('research.competitors.instagram.posts_limit'));
        $this->postJson($researchRunsUrl, ['type' => 'instagram'])->assertConflict()
            ->assertJsonPath('code', 'research_already_running');

        Queue::assertPushedOn('research_queue', ResearchCompetitorInstagramJob::class);
        Queue::assertPushed(ResearchCompetitorInstagramJob::class, 1);
    }

}

<?php

namespace Tests\Feature\Knowledge;

use Tests\TestCase;
use App\Models\Brand;
use App\Services\UserService;
use App\Services\BrandService;
use Database\Factories\UserFactory;
use Database\Factories\ClientFactory;
use PHPUnit\Framework\Attributes\Test;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class KnowledgeInsightTest extends TestCase
{

    use RefreshDatabase;


    public static function referenceFields(): array
    {
        return [
            'sources' => ['knowledge_source_ids'],
            'parents' => ['parent_insight_ids'],
        ];
    }


    // No se pueden crear conclusiones con fuentes o padres de otra marca del mismo cliente, ni reemplazar una
    // referencia propia por una ajena al actualizar.
    #[Test]
    #[DataProvider('referenceFields')]
    public function rejects_foreign_references_on_creation_and_update(string $field): void
    {
        $brand = $this->createBrand();
        $siblingBrand = resolve(BrandService::class)->create($brand->client, ['name' => 'Segunda marca']);
        $references = $this->createReferences($siblingBrand);
        $service = resolve(KnowledgeInsightService::class);
        $insight = $service->create($brand, $this->insightAttributes());

        try {
            $service->create($brand, [...$this->insightAttributes(), $field => $references[$field]]);
            $this->fail('La creación con una referencia ajena debía fallar.');
        } catch (ModelNotFoundException) {
            $this->assertSame([$insight->id], $service->list($brand)->modelKeys());
        }

        try {
            $service->update($brand, $insight->id, [$field => $references[$field]]);
            $this->fail('La actualización con una referencia ajena debía fallar.');
        } catch (ModelNotFoundException) {
            $this->assertNull($insight->fresh()->getAttribute($field));
        }
    }


    // La pantalla del sitio web recibe el análisis y las conclusiones vigentes (activas y corregidas) de la marca
    // autenticada, sin las de otros tipos ni de otras marcas.
    #[Test]
    public function returns_the_current_website_analysis_and_insights(): void
    {
        $user = UserFactory::new()->owner()->create();
        $brand = resolve(BrandService::class)->create($user->client, ['name' => 'Mi marca']);
        $otherBrand = $this->createBrand();
        $service = resolve(KnowledgeInsightService::class);
        $active = $service->create($brand, ['type' => 'website_insight', 'body' => 'Activa']);
        $superseded = $service->create($brand, [
            'type' => 'website_insight', 'body' => 'Corregida', 'status' => 'superseded',
        ]);
        $analysis = $service->create($brand, ['type' => 'website_brand_analysis', 'body' => 'Resumen']);
        $service->create($brand, ['type' => 'website_insight', 'body' => 'Vieja', 'status' => 'outdated']);
        $service->create($brand, ['type' => 'website_insight', 'body' => 'Rechazada', 'status' => 'rejected']);
        $service->create($brand, ['type' => 'strength', 'body' => 'Otro tipo']);
        $service->create($otherBrand, ['type' => 'website_insight', 'body' => 'Otra marca']);
        $credentials = resolve(UserService::class)->createApiToken($user);

        $response = $this->withToken($credentials['token'])->getJson('/api/knowledge-insights/website');

        $response->assertOk()->assertJsonPath('data.analysis.id', $analysis->id);
        $this->assertSame([$active->id, $superseded->id], array_column($response->json('data.insights'), 'id'));
    }


    private function createBrand(): Brand
    {
        return resolve(BrandService::class)->create(ClientFactory::new()->create(), ['name' => 'Marca de prueba']);
    }


    private function insightAttributes(): array
    {
        return [
            'type' => 'strength',
            'body' => 'Ofrece asesoramiento',
        ];
    }


    private function createReferences(Brand $brand): array
    {
        $source = resolve(KnowledgeSourceService::class)->create($brand, [
            'type' => 'audio',
            'title' => 'Audio inicial',
        ]);
        $insight = resolve(KnowledgeInsightService::class)->create($brand, $this->insightAttributes());

        return ['knowledge_source_ids' => [$source->id], 'parent_insight_ids' => [$insight->id]];
    }

}

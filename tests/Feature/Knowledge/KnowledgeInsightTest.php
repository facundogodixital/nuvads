<?php

namespace Tests\Feature\Knowledge;

use Tests\TestCase;
use App\Models\Brand;
use App\Services\BrandService;
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


    // Corregir y quitar una corrección conserva el original y sincroniza el indicador, incluso ante valores contrarios.
    #[Test]
    public function preserves_original_and_synchronizes_user_corrections(): void
    {
        $brand = $this->createBrand();
        $service = resolve(KnowledgeInsightService::class);
        $insight = $service->create($brand, [...$this->insightAttributes(), 'is_user_edited' => true])->fresh();

        $this->assertFalse($insight->is_user_edited);
        $this->assertSame('Ofrece asesoramiento', $insight->getEffectiveBody());

        $correctedInsight = $service->update($brand, $insight->id, [
            'user_body' => 'Asesora sobre cantidades',
            'is_user_edited' => false,
        ])->fresh();

        $this->assertTrue($correctedInsight->is_user_edited);
        $this->assertSame('Ofrece asesoramiento', $correctedInsight->body);
        $this->assertSame('Asesora sobre cantidades', $correctedInsight->getEffectiveBody());

        $retainedInsight = $service->update($brand, $insight->id, ['is_user_edited' => false])->fresh();
        $this->assertTrue($retainedInsight->is_user_edited);

        $restoredInsight = $service->update($brand, $insight->id, ['user_body' => null])->fresh();
        $this->assertFalse($restoredInsight->is_user_edited);
        $this->assertNull($restoredInsight->user_body);
        $this->assertSame('Ofrece asesoramiento', $restoredInsight->getEffectiveBody());
    }


    public static function referenceFields(): array
    {
        return [
            'source' => ['knowledge_source_id'],
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


    private function createBrand(): Brand
    {
        return resolve(BrandService::class)->create(ClientFactory::new()->create(), ['name' => 'Marca de prueba']);
    }


    private function insightAttributes(): array
    {
        return [
            'type' => 'strength',
            'body' => 'Ofrece asesoramiento',
            'run_id' => 'f36fdba6-7b8b-4b11-9e82-e5c98b4e12ba',
        ];
    }


    private function createReferences(Brand $brand): array
    {
        $source = resolve(KnowledgeSourceService::class)->create($brand, [
            'type' => 'audio',
            'title' => 'Audio inicial',
        ]);
        $insight = resolve(KnowledgeInsightService::class)->create($brand, $this->insightAttributes());

        return ['knowledge_source_id' => $source->id, 'parent_insight_ids' => [$insight->id]];
    }

}

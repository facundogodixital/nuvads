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


    // Las conclusiones conservan su fuente y los padres JSON, junto con los tipos y valores por defecto.
    #[Test]
    public function persists_sources_and_both_insight_levels(): void
    {
        $brand = $this->createBrand();
        $sourceService = resolve(KnowledgeSourceService::class);
        $insightService = resolve(KnowledgeInsightService::class);
        $source = $sourceService->create($brand, [
            'type' => 'audio',
            'title' => 'Audio inicial',
            'payload' => ['duration_seconds' => 90],
            'captured_at' => '2026-09-20 10:00:00',
        ])->fresh();

        $firstInsight = $insightService->create($brand, [
            ...$this->insightAttributes(),
            'confidence' => '0.85',
            'knowledge_source_id' => $source->id,
        ])->fresh();
        $secondInsight = $insightService->create($brand, [
            ...$this->insightAttributes(),
            'level' => 2,
            'parent_insight_ids' => [$firstInsight->id],
            'payload' => ['mentions' => 3],
        ])->fresh();

        $this->assertSame('pending', $source->status);
        $this->assertSame(['duration_seconds' => 90], $source->payload);
        $this->assertSame('2026-09-20 10:00:00', $source->captured_at->format('Y-m-d H:i:s'));
        $this->assertTrue($firstInsight->knowledgeSource->is($source));
        $this->assertSame([$firstInsight->id], $source->knowledgeInsights->modelKeys());
        $this->assertSame(1, $firstInsight->level);
        $this->assertSame('0.85', $firstInsight->confidence);
        $this->assertSame('active', $firstInsight->status);
        $this->assertFalse($firstInsight->is_user_edited);
        $this->assertNull($firstInsight->parent_insight_ids);
        $this->assertSame(2, $secondInsight->level);
        $this->assertNull($secondInsight->knowledge_source_id);
        $this->assertSame([$firstInsight->id], $secondInsight->parent_insight_ids);
        $this->assertSame(['mentions' => 3], $secondInsight->payload);
    }


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


    // No se pueden crear conclusiones con fuentes o padres de otra marca del mismo cliente.
    #[Test]
    #[DataProvider('referenceFields')]
    public function rejects_foreign_references_on_creation(string $field): void
    {
        $brand = $this->createBrand();
        $siblingBrand = resolve(BrandService::class)->create($brand->client, ['name' => 'Segunda marca']);
        $references = $this->createReferences($siblingBrand);
        $service = resolve(KnowledgeInsightService::class);

        $this->expectException(ModelNotFoundException::class);
        try {
            $service->create($brand, [...$this->insightAttributes(), $field => $references[$field]]);
        } finally {
            $this->assertCount(0, $service->list($brand));
        }
    }


    // No se puede reemplazar una referencia propia por otra de un cliente ajeno durante una actualización.
    #[Test]
    #[DataProvider('referenceFields')]
    public function rejects_foreign_references_on_update(string $field): void
    {
        $brand = $this->createBrand();
        $references = $this->createReferences($this->createBrand());
        $service = resolve(KnowledgeInsightService::class);
        $insight = $service->create($brand, $this->insightAttributes());

        $this->expectException(ModelNotFoundException::class);
        try {
            $service->update($brand, $insight->id, [$field => $references[$field]]);
        } finally {
            $this->assertNull($insight->fresh()->getAttribute($field));
        }
    }


    // El borrado lógico de una fuente conserva el vínculo; el borrado físico lo deja en null sin borrar la conclusión.
    #[Test]
    public function retains_insight_when_source_is_deleted(): void
    {
        $brand = $this->createBrand();
        $sourceService = resolve(KnowledgeSourceService::class);
        $source = $sourceService->create($brand, ['type' => 'audio', 'title' => 'Audio inicial']);
        $insight = resolve(KnowledgeInsightService::class)->create($brand, [
            ...$this->insightAttributes(),
            'knowledge_source_id' => $source->id,
        ]);

        $sourceService->delete($brand, $source->id);
        $this->assertSame($source->id, $insight->fresh()->knowledge_source_id);

        $source->forceDelete();
        $this->assertNull($insight->fresh()->knowledge_source_id);
        $this->assertNull($insight->fresh()->deleted_at);
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

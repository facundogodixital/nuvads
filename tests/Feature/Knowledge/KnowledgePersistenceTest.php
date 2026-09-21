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


class KnowledgePersistenceTest extends TestCase
{

    use RefreshDatabase;


    public static function knowledgeDomains(): array
    {
        return [
            'sources' => [KnowledgeSourceService::class, [
                'type' => 'audio',
                'title' => 'Audio inicial',
            ], ['title' => 'Audio corregido']],
            'insights' => [KnowledgeInsightService::class, [
                'type' => 'strength',
                'body' => 'Ofrece asesoramiento',
                'run_id' => 'f36fdba6-7b8b-4b11-9e82-e5c98b4e12ba',
            ], ['user_body' => 'Asesora sobre cantidades']],
        ];
    }


    // La marca explícita determina el cliente y la pertenencia, incluso con atributos ajenos en altas y cambios.
    #[Test]
    #[DataProvider('knowledgeDomains')]
    public function persists_records_under_explicit_brand(string $serviceClass, array $attributes, array $changes): void
    {
        $brand = $this->createBrand();
        $otherBrand = $this->createBrand();
        $service = resolve($serviceClass);
        $attributes['brand_id'] = $otherBrand->id;
        $attributes['client_id'] = $otherBrand->client_id;

        $record = $service->create($brand, $attributes);
        $persistedRecord = $record->fresh();

        $this->assertSame($brand->id, $persistedRecord->brand_id);
        $this->assertSame($brand->client_id, $persistedRecord->client_id);
        $this->assertTrue($persistedRecord->brand->is($brand));
        $this->assertTrue($persistedRecord->client->is($brand->client));
        $this->assertNotNull($persistedRecord->created_at);
        $this->assertNotNull($persistedRecord->updated_at);
        $this->assertNull($persistedRecord->deleted_at);

        $changes['brand_id'] = $otherBrand->id;
        $changes['client_id'] = $otherBrand->client_id;
        $updatedRecord = $service->update($brand, $record->id, $changes)->fresh();

        $this->assertSame($brand->id, $updatedRecord->brand_id);
        $this->assertSame($brand->client_id, $updatedRecord->client_id);
        unset($changes['brand_id'], $changes['client_id']);
        foreach ($changes as $field => $value) {
            $this->assertSame($value, $updatedRecord->getAttribute($field));
        }
    }


    // Las consultas excluyen otras marcas del mismo cliente, otros clientes y registros con baja lógica.
    #[Test]
    #[DataProvider('knowledgeDomains')]
    public function isolates_reads_and_soft_deletes(string $serviceClass, array $attributes, array $changes): void
    {
        $brand = $this->createBrand();
        $otherBrand = $this->createBrand();
        $siblingBrand = resolve(BrandService::class)->create($brand->client, ['name' => 'Segunda marca']);
        $service = resolve($serviceClass);
        $record = $service->create($brand, $attributes);
        $otherRecord = $service->create($otherBrand, $attributes);
        $siblingRecord = $service->create($siblingBrand, $attributes);

        $this->assertSame($record->id, $service->find($brand, $record->id)->id);
        $this->assertNull($service->find($brand, $otherRecord->id));
        $this->assertNull($service->find($brand, $siblingRecord->id));
        $this->assertSame([$record->id], $service->list($brand)->modelKeys());

        $this->assertTrue($service->delete($brand, $record->id));

        $this->assertSoftDeleted($record);
        $this->assertNull($service->find($brand, $record->id));
        $this->assertCount(0, $service->list($brand));
        $this->assertNotNull($service->find($otherBrand, $otherRecord->id));
    }


    // Una marca no puede modificar registros de otra, aunque pertenezcan al mismo cliente.
    #[Test]
    #[DataProvider('knowledgeDomains')]
    public function refuses_foreign_updates(string $serviceClass, array $attributes, array $changes): void
    {
        $brand = $this->createBrand();
        $siblingBrand = resolve(BrandService::class)->create($brand->client, ['name' => 'Segunda marca']);
        $service = resolve($serviceClass);
        $record = $service->create($siblingBrand, $attributes);
        $originalAttributes = $record->fresh()->getAttributes();

        $this->expectException(ModelNotFoundException::class);
        try {
            $service->update($brand, $record->id, $changes);
        } finally {
            $this->assertSame($originalAttributes, $record->fresh()->getAttributes());
        }
    }


    // Un intento de borrar un registro de otro cliente falla y conserva el registro activo.
    #[Test]
    #[DataProvider('knowledgeDomains')]
    public function refuses_foreign_deletes(string $serviceClass, array $attributes, array $changes): void
    {
        $brand = $this->createBrand();
        $otherBrand = $this->createBrand();
        $service = resolve($serviceClass);
        $record = $service->create($otherBrand, $attributes);

        $this->expectException(ModelNotFoundException::class);
        try {
            $service->delete($brand, $record->id);
        } finally {
            $this->assertNull($record->fresh()->deleted_at);
        }
    }


    private function createBrand(): Brand
    {
        return resolve(BrandService::class)->create(ClientFactory::new()->create(), ['name' => 'Marca de prueba']);
    }

}

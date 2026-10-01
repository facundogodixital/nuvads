<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\ContentType;
use App\Repositories\ContentTypeRepository;
use Illuminate\Database\Eloquent\Collection;
use App\Services\ContentTypeInputs\ContentTypeInput;


class ContentTypeService
{

    private ContentTypeRepository $contentTypeRepository;


    public function __construct(ContentTypeRepository $contentTypeRepository)
    {
        $this->contentTypeRepository = $contentTypeRepository;
    }


    public function create(array $attributes): ContentType
    {
        return $this->contentTypeRepository->create($attributes);
    }


    // El tipo activo con ese ID, o null si no existe o está rotado.
    public function find(int $contentTypeId): ?ContentType
    {
        return $this->contentTypeRepository->find($contentTypeId);
    }


    public function list(): Collection
    {
        return $this->contentTypeRepository->list();
    }


    // Los tipos activos, cada uno con is_available, si la marca lo puede usar, y unavailable_reason, qué le falta
    // cuando no puede, o null. No son columnas: solo viajan en la respuesta.
    public function listWithAvailability(Brand $brand): Collection
    {
        return $this->list()->each(function (ContentType $contentType) use ($brand): void {
            $unavailableReason = $this->getUnavailableReason($brand, $contentType);
            $contentType->setAttribute('is_available', $unavailableReason === null);
            $contentType->setAttribute('unavailable_reason', $unavailableReason);
        });
    }


    public function findOneByKey(string $key): ?ContentType
    {
        return $this->contentTypeRepository->findOneByKey($key);
    }


    // Por qué la marca no puede usar el tipo, o null si puede: puede cuando al menos una de sus entradas que sostienen
    // ideas tiene material. Las demás, como el puntaje, son datos de apoyo y no alcanzan solas. Si ninguna entrada del
    // tipo sostiene ideas todavía, el tipo está por llegar. Cuando faltan varias, vale el texto de la primera.
    public function getUnavailableReason(Brand $brand, ContentType $contentType): ?string
    {
        $supportingInputs = array_values(array_filter(
            $this->getInputs($contentType),
            fn (ContentTypeInput $contentTypeInput): bool => $contentTypeInput->canSupportIdeas(),
        ));
        if ($supportingInputs === []) {
            return 'Disponible pronto';
        }

        foreach ($supportingInputs as $contentTypeInput) {
            $hasMaterial = !$contentTypeInput->isEmpty($brand);
            if ($hasMaterial) {
                return null;
            }
        }

        return $supportingInputs[0]->getMissingMaterialMessage($brand);
    }


    // Las entradas del tipo que ya tienen clase, en el orden de inputs. Un nombre sin clase en config/content.php
    // todavía no existe y no cuenta.
    public function getInputs(ContentType $contentType): array
    {
        $inputClasses = config('content.inputs');

        $contentTypeInputs = [];
        foreach ($contentType->inputs as $inputName) {
            $hasInputClass = isset($inputClasses[$inputName]);
            if ($hasInputClass) {
                $contentTypeInputs[] = resolve($inputClasses[$inputName]);
            }
        }

        return $contentTypeInputs;
    }

}

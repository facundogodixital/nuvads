<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\KnowledgeSourceRepository;


class KnowledgeSourceService
{

    private KnowledgeSourceRepository $knowledgeSourceRepository;


    public function __construct(KnowledgeSourceRepository $knowledgeSourceRepository)
    {
        $this->knowledgeSourceRepository = $knowledgeSourceRepository;
    }


    public function create(Brand $brand, array $attributes): KnowledgeSource
    {
        return $this->knowledgeSourceRepository->create($brand, $attributes);
    }


    public function update(Brand $brand, int $knowledgeSourceId, array $attributes): KnowledgeSource
    {
        return $this->knowledgeSourceRepository->update($brand, $knowledgeSourceId, $attributes);
    }


    public function find(Brand $brand, int $knowledgeSourceId): ?KnowledgeSource
    {
        return $this->knowledgeSourceRepository->find($brand, $knowledgeSourceId);
    }


    public function list(Brand $brand): Collection
    {
        return $this->knowledgeSourceRepository->list($brand);
    }


    public function delete(Brand $brand, int $knowledgeSourceId): bool
    {
        return $this->knowledgeSourceRepository->delete($brand, $knowledgeSourceId);
    }


    public function findByIds(Brand $brand, array $knowledgeSourceIds): Collection
    {
        return $this->knowledgeSourceRepository->findByIds($brand, $knowledgeSourceIds);
    }


    public function findByTypes(Brand $brand, array $types): Collection
    {
        return $this->knowledgeSourceRepository->findByTypes($brand, $types);
    }


    // Borra las fuentes de un tipo de la marca, salvo las indicadas. Devuelve cuántas borró.
    public function deleteByTypeExceptIds(Brand $brand, string $type, array $keptKnowledgeSourceIds): int
    {
        return $this->knowledgeSourceRepository->deleteByTypeExceptIds($brand, $type, $keptKnowledgeSourceIds);
    }

}

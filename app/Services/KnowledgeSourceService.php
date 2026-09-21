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

}

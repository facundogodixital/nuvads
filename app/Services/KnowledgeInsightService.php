<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeInsight;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\KnowledgeInsightRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class KnowledgeInsightService
{

    private KnowledgeInsightRepository $knowledgeInsightRepository;


    public function __construct(KnowledgeInsightRepository $knowledgeInsightRepository)
    {
        $this->knowledgeInsightRepository = $knowledgeInsightRepository;
    }


    public function create(Brand $brand, array $attributes): KnowledgeInsight
    {
        $this->checkReferencesBelongToBrand($brand, $attributes);
        $attributes['is_user_edited'] = isset($attributes['user_body']);

        return $this->knowledgeInsightRepository->create($brand, $attributes);
    }


    public function update(Brand $brand, int $knowledgeInsightId, array $attributes): KnowledgeInsight
    {
        $this->checkReferencesBelongToBrand($brand, $attributes);

        // El indicador depende de la corrección; no se modifica por separado.
        unset($attributes['is_user_edited']);
        if (array_key_exists('user_body', $attributes)) {
            $attributes['is_user_edited'] = $attributes['user_body'] !== null;
        }

        return $this->knowledgeInsightRepository->update($brand, $knowledgeInsightId, $attributes);
    }


    public function find(Brand $brand, int $knowledgeInsightId): ?KnowledgeInsight
    {
        return $this->knowledgeInsightRepository->find($brand, $knowledgeInsightId);
    }


    public function list(Brand $brand): Collection
    {
        return $this->knowledgeInsightRepository->list($brand);
    }


    public function delete(Brand $brand, int $knowledgeInsightId): bool
    {
        return $this->knowledgeInsightRepository->delete($brand, $knowledgeInsightId);
    }


    private function checkReferencesBelongToBrand(Brand $brand, array $attributes): void
    {
        $knowledgeSourceId = $attributes['knowledge_source_id'] ?? null;
        if ($knowledgeSourceId !== null) {
            $knowledgeSourceService = resolve(KnowledgeSourceService::class);
            $knowledgeSource = $knowledgeSourceService->find($brand, $knowledgeSourceId);
            if ($knowledgeSource === null) {
                throw (new ModelNotFoundException())->setModel(KnowledgeSource::class, [$knowledgeSourceId]);
            }
        }

        $parentInsightIds = $attributes['parent_insight_ids'] ?? [];
        foreach ($parentInsightIds as $parentInsightId) {
            $parentInsight = $this->knowledgeInsightRepository->find($brand, $parentInsightId);
            if ($parentInsight === null) {
                throw (new ModelNotFoundException())->setModel(KnowledgeInsight::class, [$parentInsightId]);
            }
        }
    }

}

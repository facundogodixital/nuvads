<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\KnowledgeInsight;
use Illuminate\Database\Eloquent\Collection;


class KnowledgeInsightRepository
{


    public function create(Brand $brand, array $attributes): KnowledgeInsight
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;

        return KnowledgeInsight::query()->create($attributes);
    }


    public function update(Brand $brand, int $knowledgeInsightId, array $attributes): KnowledgeInsight
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;
        $knowledgeInsight = KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->findOrFail($knowledgeInsightId);

        $knowledgeInsight->fill($attributes);
        $knowledgeInsight->save();

        return $knowledgeInsight;
    }


    public function find(Brand $brand, int $knowledgeInsightId): ?KnowledgeInsight
    {
        return KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->find($knowledgeInsightId);
    }


    public function list(Brand $brand): Collection
    {
        return KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->orderBy('id')
            ->get();
    }


    public function delete(Brand $brand, int $knowledgeInsightId): bool
    {
        $knowledgeInsight = KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->findOrFail($knowledgeInsightId);

        return $knowledgeInsight->delete();
    }


    public function findByTypesAndStatuses(Brand $brand, array $types, array $statuses): Collection
    {
        return KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->whereIn('type', $types)
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->get();
    }


    public function updateStatusByTypeAndStatus(Brand $brand, string $type, string $status, string $newStatus): int
    {
        return KnowledgeInsight::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->where('type', $type)
            ->where('status', $status)
            ->update(['status' => $newStatus]);
    }

}

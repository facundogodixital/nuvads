<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Collection;


class KnowledgeSourceRepository
{


    public function create(Brand $brand, array $attributes): KnowledgeSource
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;

        return KnowledgeSource::query()->create($attributes);
    }


    public function update(Brand $brand, int $knowledgeSourceId, array $attributes): KnowledgeSource
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;
        $knowledgeSource = KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->findOrFail($knowledgeSourceId);

        $knowledgeSource->fill($attributes);
        $knowledgeSource->save();

        return $knowledgeSource;
    }


    public function find(Brand $brand, int $knowledgeSourceId): ?KnowledgeSource
    {
        return KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->find($knowledgeSourceId);
    }


    public function list(Brand $brand): Collection
    {
        return KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->orderBy('id')
            ->get();
    }


    public function delete(Brand $brand, int $knowledgeSourceId): bool
    {
        $knowledgeSource = KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->findOrFail($knowledgeSourceId);

        return $knowledgeSource->delete();
    }


    public function findByIds(Brand $brand, array $knowledgeSourceIds): Collection
    {
        return KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->whereIn('id', $knowledgeSourceIds)
            ->orderBy('id')->get();
    }


    public function deleteByTypeExceptIds(Brand $brand, string $type, array $keptKnowledgeSourceIds): int
    {
        return KnowledgeSource::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->where('type', $type)
            ->whereNotIn('id', $keptKnowledgeSourceIds)
            ->delete();
    }

}

<?php

namespace App\Repositories;

use App\Models\Competitor;
use App\Models\CompetitorSource;
use Illuminate\Database\Eloquent\Collection;


class CompetitorSourceRepository
{


    public function create(Competitor $competitor, array $attributes): CompetitorSource
    {
        $attributes['competitor_id'] = $competitor->id;
        $attributes['client_id'] = $competitor->client_id;

        return CompetitorSource::query()->create($attributes);
    }


    public function findByIds(Competitor $competitor, array $competitorSourceIds): Collection
    {
        return CompetitorSource::query()
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->whereIn('id', $competitorSourceIds)
            ->orderBy('id')
            ->get();
    }


    public function deleteByTypeExceptIds(Competitor $competitor, string $type, array $keptCompetitorSourceIds): int
    {
        return CompetitorSource::query()
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->where('type', $type)
            ->whereNotIn('id', $keptCompetitorSourceIds)
            ->delete();
    }

}

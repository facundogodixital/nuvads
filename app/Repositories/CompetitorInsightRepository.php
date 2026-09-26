<?php

namespace App\Repositories;

use App\Models\Competitor;
use App\Models\CompetitorInsight;
use Illuminate\Database\Eloquent\Collection;


class CompetitorInsightRepository
{


    public function create(Competitor $competitor, array $attributes): CompetitorInsight
    {
        $attributes['competitor_id'] = $competitor->id;
        $attributes['client_id'] = $competitor->client_id;

        return CompetitorInsight::query()->create($attributes);
    }


    public function findByTypesAndStatuses(Competitor $competitor, array $types, array $statuses): Collection
    {
        return CompetitorInsight::query()
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->whereIn('type', $types)
            ->whereIn('status', $statuses)
            ->orderBy('id')
            ->get();
    }


    public function updateStatusByTypeAndStatus(
        Competitor $competitor,
        string $type,
        string $status,
        string $newStatus,
    ): int {
        return CompetitorInsight::query()
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->where('type', $type)
            ->where('status', $status)
            ->update(['status' => $newStatus]);
    }

}

<?php

namespace App\Repositories;

use App\Models\Competitor;
use App\Models\CompetitorResearchRun;


class CompetitorResearchRunRepository
{


    public function create(Competitor $competitor, array $attributes): CompetitorResearchRun
    {
        $attributes['competitor_id'] = $competitor->id;
        $attributes['client_id'] = $competitor->client_id;

        return CompetitorResearchRun::query()->create($attributes);
    }


    public function update(CompetitorResearchRun $researchRun, array $attributes): CompetitorResearchRun
    {
        $researchRun->fill($attributes);
        $researchRun->save();

        return $researchRun;
    }


    public function find(int $researchRunId): ?CompetitorResearchRun
    {
        return CompetitorResearchRun::query()->find($researchRunId);
    }


    public function findOneActiveForCompetitor(Competitor $competitor, string $type): ?CompetitorResearchRun
    {
        return CompetitorResearchRun::query()
            ->where('type', $type)
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->whereIn('status', ['pending', 'scraping', 'analyzing'])
            ->latest('id')->first();
    }


    public function findOneLatestForCompetitor(Competitor $competitor, string $type): ?CompetitorResearchRun
    {
        return CompetitorResearchRun::query()
            ->where('type', $type)
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->latest('id')->first();
    }


    public function findOneCompletedForCompetitor(Competitor $competitor, string $type): ?CompetitorResearchRun
    {
        return CompetitorResearchRun::query()
            ->where('type', $type)
            ->where('status', 'completed')
            ->where('competitor_id', $competitor->id)
            ->where('client_id', $competitor->client_id)
            ->latest('id')->first();
    }

}

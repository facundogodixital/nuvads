<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\ResearchRun;


class ResearchRunRepository
{


    public function create(Brand $brand, array $attributes): ResearchRun
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;

        return ResearchRun::query()->create($attributes);
    }


    public function find(int $researchRunId): ?ResearchRun
    {
        return ResearchRun::query()->find($researchRunId);
    }


    public function updateIfStatusMatches(int $researchRunId, array $statuses, array $attributes): ?ResearchRun
    {
        ResearchRun::query()->whereKey($researchRunId)->whereIn('status', $statuses)->update($attributes);

        return $this->find($researchRunId);
    }


    public function findForBrand(Brand $brand, int $researchRunId): ResearchRun
    {
        return ResearchRun::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->with('knowledgeInsights')
            ->findOrFail($researchRunId);
    }


    public function findOneActiveForBrand(Brand $brand, string $type): ?ResearchRun
    {
        return ResearchRun::query()
            ->where('type', $type)
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->whereIn('status', ['pending', 'scraping', 'analyzing'])
            ->latest('id')->first();
    }


    public function findOneLatestForBrand(Brand $brand, string $type): ?ResearchRun
    {
        return ResearchRun::query()
            ->where('type', $type)
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->latest('id')->first();
    }


    public function findOneCompletedForBrand(Brand $brand, string $type): ?ResearchRun
    {
        return ResearchRun::query()
            ->where('type', $type)
            ->where('status', 'completed')
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->latest('id')->first();
    }


    public function update(ResearchRun $researchRun, array $attributes): ResearchRun
    {
        $researchRun->fill($attributes);
        $researchRun->save();

        return $researchRun;
    }

}

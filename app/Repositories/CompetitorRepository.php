<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\Competitor;
use Illuminate\Database\Eloquent\Collection;


class CompetitorRepository
{


    public function create(Brand $brand, array $attributes): Competitor
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;

        return Competitor::query()->create($attributes);
    }


    public function update(Competitor $competitor, array $attributes): Competitor
    {
        $competitor->fill($attributes);
        $competitor->save();

        return $competitor;
    }


    public function delete(Competitor $competitor): bool
    {
        return $competitor->delete();
    }


    public function find(int $competitorId): Competitor
    {
        return Competitor::query()->findOrFail($competitorId);
    }


    public function findForBrand(Brand $brand, int $competitorId): Competitor
    {
        return Competitor::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->findOrFail($competitorId);
    }


    public function list(Brand $brand): Collection
    {
        return Competitor::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->orderBy('id')
            ->get();
    }

}

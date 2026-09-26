<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Models\Competitor;
use Illuminate\Support\Facades\DB;
use App\Repositories\CompetitorRepository;
use Illuminate\Database\Eloquent\Collection;
use App\Services\Dispatchers\ResearchDispatcherService;


class CompetitorService
{

    // Lo que sabemos del competidor: cada análisis de sus fuentes mezcla estos campos con lo que ya tenían.
    const array KNOWLEDGE_FIELDS = [
        'competitor_offer_description',
        'competitor_customers_description',
        'competitor_strengths_description',
        'competitor_weaknesses_description',
        'competitor_communication_description',
        'competitor_differentiators_description',
    ];

    private CompetitorRepository $competitorRepository;


    public function __construct(CompetitorRepository $competitorRepository)
    {
        $this->competitorRepository = $competitorRepository;
    }


    public function create(Brand $brand, array $attributes): Competitor
    {
        return $this->competitorRepository->create($brand, $attributes);
    }


    public function update(Competitor $competitor, array $attributes): Competitor
    {
        return $this->competitorRepository->update($competitor, $attributes);
    }


    // Sin el competidor, lo que la marca sabe de su competencia se vuelve a calcular con los que quedan.
    public function delete(Competitor $competitor): bool
    {
        DB::beginTransaction();
        try {
            $isDeleted = $this->competitorRepository->delete($competitor);
            // La queue database comparte la transacción: el borrado y su job se guardan juntos.
            resolve(ResearchDispatcherService::class)->dispatchResearchBrandCompetitionJob($competitor->brand_id);
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $isDeleted;
    }


    public function find(int $competitorId): Competitor
    {
        return $this->competitorRepository->find($competitorId);
    }


    public function findForBrand(Brand $brand, int $competitorId): Competitor
    {
        return $this->competitorRepository->findForBrand($brand, $competitorId);
    }


    public function list(Brand $brand): Collection
    {
        return $this->competitorRepository->list($brand);
    }

}

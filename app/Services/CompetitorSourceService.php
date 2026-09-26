<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorSource;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\CompetitorSourceRepository;


class CompetitorSourceService
{

    private CompetitorSourceRepository $competitorSourceRepository;


    public function __construct(CompetitorSourceRepository $competitorSourceRepository)
    {
        $this->competitorSourceRepository = $competitorSourceRepository;
    }


    public function create(Competitor $competitor, array $attributes): CompetitorSource
    {
        return $this->competitorSourceRepository->create($competitor, $attributes);
    }


    public function findByIds(Competitor $competitor, array $competitorSourceIds): Collection
    {
        return $this->competitorSourceRepository->findByIds($competitor, $competitorSourceIds);
    }


    // Borra las fuentes de un tipo del competidor, salvo las indicadas. Devuelve cuántas borró.
    public function deleteByTypeExceptIds(Competitor $competitor, string $type, array $keptCompetitorSourceIds): int
    {
        return $this->competitorSourceRepository->deleteByTypeExceptIds(
            $competitor, $type, $keptCompetitorSourceIds,
        );
    }

}

<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\JsonResponse;
use App\Services\CompetitorService;
use App\Http\Requests\AuthenticatedRequest;
use App\Services\CompetitorResearchRunService;
use App\Http\Requests\Competitors\CreateCompetitorRequest;
use App\Http\Requests\Competitors\UpdateCompetitorRequest;


class CompetitorController extends ApiController
{


    // Cada competidor viaja con el estado de sus cuatro fuentes, en research_statuses por ID del competidor.
    public function list(AuthenticatedRequest $request): JsonResponse
    {
        $competitors = resolve(CompetitorService::class)->list($request->brand);
        $researchStatuses = resolve(CompetitorResearchRunService::class)->getResearchStatusesByCompetitorId(
            $competitors,
        );
        return $this->respond(['competitors' => $competitors, 'research_statuses' => $researchStatuses]);
    }


    public function create(CreateCompetitorRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $competitor = resolve(CompetitorService::class)->create($request->brand, $attributes);
        return $this->respond($competitor, 201);
    }


    // El competidor viaja con el estado de sus cuatro fuentes, en research_statuses por tipo.
    public function find(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitor = resolve(CompetitorService::class)->findForBrand($request->brand, $competitorId);
        $researchStatuses = resolve(CompetitorResearchRunService::class)->getResearchStatusesByType($competitor);
        return $this->respond(['competitor' => $competitor, 'research_statuses' => $researchStatuses]);
    }


    public function update(UpdateCompetitorRequest $request, int $competitorId): JsonResponse
    {
        $attributes = $request->validated();
        $competitorService = resolve(CompetitorService::class);
        $competitor = $competitorService->findForBrand($request->brand, $competitorId);
        $competitor = $competitorService->update($competitor, $attributes);
        return $this->respond($competitor);
    }


    public function delete(AuthenticatedRequest $request, int $competitorId): JsonResponse
    {
        $competitorService = resolve(CompetitorService::class);
        $competitor = $competitorService->findForBrand($request->brand, $competitorId);
        $competitorService->delete($competitor);
        return $this->respond();
    }

}

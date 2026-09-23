<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Models\ResearchRun;
use Illuminate\Support\Str;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use App\Repositories\ResearchRunRepository;
use App\Services\Dispatchers\ResearchDispatcherService;


class ResearchRunService
{

    private ResearchRunRepository $researchRunRepository;


    public function __construct(ResearchRunRepository $researchRunRepository)
    {
        $this->researchRunRepository = $researchRunRepository;
    }


    public function create(Brand $brand, array $attributes): ResearchRun
    {
        $activeResearchRun = $this->findOneActiveForBrand($brand, $attributes['type']);
        if ($activeResearchRun !== null) {
            throw new ApiException(409, 'research_already_running', 'Ya hay una investigación web en curso.');
        }
        if ($brand->website_url === null) {
            throw new ApiException(422, 'website_missing', 'Guarda el sitio web antes de analizarlo.');
        }

        DB::beginTransaction();
        try {
            $researchRun = $this->researchRunRepository->create($brand, [
                'status' => 'pending',
                'knowledge_source_ids' => [],
                'type' => $attributes['type'],
                'run_id' => (string) Str::uuid(),
                'input' => [
                    'url' => $brand->website_url,
                    'model' => config('research.website.analysis_model'), // gpt-6-luna
                    // Por defecto el análisis pisa la marca; con overwrite en false solo completa los vacíos.
                    'overwrite' => $attributes['overwrite'] ?? true,
                ],
            ]);
            // La queue database comparte la transacción: la ejecución y su job se guardan juntos.
            resolve(ResearchDispatcherService::class)->dispatchResearchWebsiteJob($researchRun->id);
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $researchRun;
    }


    public function find(int $researchRunId): ?ResearchRun
    {
        return $this->researchRunRepository->find($researchRunId);
    }


    public function findForBrand(Brand $brand, int $researchRunId): ResearchRun
    {
        $researchRun = $this->researchRunRepository->findForBrand($brand, $researchRunId);
        $knowledgeSources = resolve(KnowledgeSourceService::class)->findByIds(
            $brand, $researchRun->knowledge_source_ids,
        );
        $researchRun->setRelation('knowledgeSources', $knowledgeSources);

        return $researchRun;
    }


    public function findOneActiveForBrand(Brand $brand, string $type): ?ResearchRun
    {
        return $this->researchRunRepository->findOneActiveForBrand($brand, $type);
    }


    public function getWebsiteStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'website'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'website'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'website'),
        ];
    }


    public function update(ResearchRun $researchRun, array $attributes): ResearchRun
    {
        return $this->researchRunRepository->update($researchRun, $attributes);
    }


    public function fail(int $researchRunId, string $message): ?ResearchRun
    {
        $researchRun = $this->researchRunRepository->find($researchRunId);
        if ($researchRun === null) {
            return null;
        }

        return $this->researchRunRepository->update($researchRun, [
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
        ]);
    }

}

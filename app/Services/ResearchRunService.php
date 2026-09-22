<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Models\ResearchRun;
use Illuminate\Support\Str;
use App\Helpers\ApifyHelper;
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
        DB::beginTransaction();
        try {
            // Bloquear la marca también serializa solicitudes cuando todavía no existe ninguna ejecución.
            $brand = resolve(BrandService::class)->findForUpdate($brand->id);
            $activeRun = $this->researchRunRepository->findOneActiveForBrand($brand, $attributes['type']);
            if ($activeRun !== null) {
                throw new ApiException(409, 'research_already_running', 'Ya hay una investigación web en curso.');
            }
            if ($brand->website_url === null) {
                throw new ApiException(422, 'website_missing', 'Guarda el sitio web antes de analizarlo.');
            }

            $researchRun = $this->researchRunRepository->create($brand, [
                'type' => $attributes['type'],
                'status' => 'pending',
                'run_id' => (string) Str::uuid(),
                'knowledge_source_ids' => [],
                'input' => [
                    'url' => $brand->website_url,
                    'max_pages' => ApifyHelper::DEFAULT_WEBSITE_MAX_PAGES,
                ],
            ]);
            // La queue database comparte la transacción: se guardan la ejecución y su job juntos.
            resolve(ResearchDispatcherService::class)->dispatchStartWebsiteScrapingJob($researchRun->id);
            DB::commit();

            return $researchRun;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    public function findForBrand(Brand $brand, int $researchRunId): ResearchRun
    {
        $researchRun = $this->researchRunRepository->findForBrand($brand, $researchRunId);
        $sources = resolve(KnowledgeSourceService::class)->findByIds($brand, $researchRun->knowledge_source_ids);
        $researchRun->setRelation('knowledgeSources', $sources);

        return $researchRun;
    }


    public function getWebsiteStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'website'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'website'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'website'),
        ];
    }


    public function startWebsiteScraping(int $researchRunId): ?ResearchRun
    {
        $researchRun = $this->researchRunRepository->find($researchRunId);
        $isPending = $researchRun?->status === 'pending';
        if (!$isPending) {
            return null;
        }

        $researchRun = $this->researchRunRepository->update($researchRun, [
            'status' => 'scraping',
            'started_at' => now(),
        ]);

        // Se reclama antes de llamar a Apify para no repetir una ejecución paga ante entregas duplicadas.
        $externalRun = resolve(ApifyHelper::class)->startWebsiteContentCrawler(
            [$researchRun->input['url']],
            $researchRun->input['max_pages'],
        );

        DB::beginTransaction();
        try {
            $researchRun = $this->researchRunRepository->update($researchRun, [
                'external_run_id' => $externalRun->id,
                'external_dataset_id' => $externalRun->datasetId,
            ]);
            resolve(ResearchDispatcherService::class)->dispatchCheckWebsiteScrapingJob($researchRun->id);
            DB::commit();

            return $researchRun;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    public function checkWebsiteScraping(int $researchRunId): ?ResearchRun
    {
        $researchRun = $this->researchRunRepository->find($researchRunId);
        $isScraping = $researchRun?->status === 'scraping';
        if (!$isScraping) {
            return null;
        }
        if ($researchRun->external_run_id === null) {
            return null;
        }

        $apifyHelper = resolve(ApifyHelper::class);
        $externalRun = $apifyHelper->getRun($researchRun->external_run_id);
        $hasSucceeded = $externalRun->status === 'SUCCEEDED';
        $hasFailed = in_array($externalRun->status, ['FAILED', 'ABORTED', 'TIMED-OUT'], true);
        $deadline = $researchRun->started_at->copy()->addSeconds(config('research.website.max_wait_seconds'));
        $hasExpired = $deadline->isPast();
        $pages = [];
        if ($hasSucceeded) {
            if ($externalRun->datasetId === null) {
                throw new ApiException(502, 'apify_dataset_missing', 'Apify no entregó el contenido recopilado.');
            }
            $pages = $apifyHelper->getWebsitePages($externalRun->datasetId, $researchRun->input['max_pages']);
        }

        DB::beginTransaction();
        try {
            $attributes = ['last_checked_at' => now()];
            if ($hasSucceeded) {
                $sourceIds = $this->saveWebsiteSources($researchRun, $pages);
                if ($sourceIds === []) {
                    $attributes['status'] = 'failed';
                    $attributes['finished_at'] = now();
                    $attributes['error_message'] = 'No se encontró contenido web disponible para analizar.';
                } else {
                    $attributes['status'] = 'analyzing';
                    $attributes['external_dataset_id'] = $externalRun->datasetId;
                    $attributes['knowledge_source_ids'] = $sourceIds;
                }
            } elseif ($hasFailed || $hasExpired) {
                $attributes['status'] = 'failed';
                $attributes['finished_at'] = now();
                $attributes['error_message'] = $hasFailed
                    ? 'Apify no pudo completar la recopilación del sitio web.'
                    : 'Se agotó el tiempo de espera del scraping. La ejecución externa puede seguir activa.';
            }

            $researchRun = $this->researchRunRepository->update($researchRun, $attributes);
            $dispatcher = resolve(ResearchDispatcherService::class);
            if ($researchRun->status === 'analyzing') {
                $dispatcher->dispatchAnalyzeWebsiteContentJob($researchRun->id);
            } elseif ($researchRun->status === 'scraping') {
                $dispatcher->dispatchCheckWebsiteScrapingJob($researchRun->id);
            }
            DB::commit();

            return $researchRun;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    private function saveWebsiteSources(ResearchRun $researchRun, array $pages): array
    {
        $sourceIds = [];
        $brand = $researchRun->brand;
        $sourceService = resolve(KnowledgeSourceService::class);
        foreach ($pages as $page) {
            $contentHash = hash('sha256', $page['url']."\n".$page['content']);
            $source = $sourceService->findOneByContentHash($brand, $contentHash);
            if ($source === null) {
                $source = $sourceService->create($brand, [
                    'type' => 'web_page',
                    'status' => 'ready',
                    'captured_at' => now(),
                    'content_hash' => $contentHash,
                    'payload' => $page['payload'],
                    'title' => Str::limit($page['title'], 255, ''),
                    // La URL completa se conserva en payload si no cabe en source_ref.
                    'source_ref' => Str::length($page['url']) <= 512 ? $page['url'] : null,
                ]);
            }
            // No resucitar fuentes que el usuario haya eliminado; su hash sigue siendo único.
            if (!$source->trashed()) {
                $sourceIds[] = $source->id;
            }
        }

        return array_values(array_unique($sourceIds));
    }


    public function analyzeWebsiteContent(int $researchRunId): ?ResearchRun
    {
        $researchRun = $this->researchRunRepository->find($researchRunId);
        $isAnalyzing = $researchRun?->status === 'analyzing';
        if (!$isAnalyzing) {
            return null;
        }

        $brand = $researchRun->brand;
        $sources = resolve(KnowledgeSourceService::class)->findByIds($brand, $researchRun->knowledge_source_ids);
        if ($sources->isEmpty()) {
            throw new ApiException(409, 'research_sources_missing', 'Las fuentes ya no están disponibles.');
        }

        // IA pendiente: resolver proveedor, prompt y validación antes de habilitar esta llamada.
        // $insights = resolve(WebsiteAnalysisHelper::class)->analyze($sources);
        // La llamada externa queda fuera de la transacción de persistencia.
        DB::beginTransaction();
        try {
            $insightService = resolve(KnowledgeInsightService::class);
            foreach ($sources as $source) {
                $insightService->create($brand, [
                    'type' => 'website_analysis_preview',
                    'level' => 1,
                    'status' => 'active',
                    'model' => 'mock',
                    'prompt_version' => 'mock-v1',
                    'run_id' => $researchRun->run_id,
                    'knowledge_source_id' => $source->id,
                    'body' => 'Resultado simulado. La página se recopiló, pero todavía no fue interpretada por IA.',
                    'payload' => ['is_mock' => true, 'url' => $source->payload['url']],
                ]);
            }
            // Los resultados nuevos no modifican los hallazgos anteriores ni sus correcciones manuales.
            $researchRun = $this->researchRunRepository->update($researchRun, [
                'status' => 'completed',
                'finished_at' => now(),
            ]);
            DB::commit();

            return $researchRun;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    public function fail(int $researchRunId, array $expectedStatuses, string $message): ?ResearchRun
    {
        return $this->researchRunRepository->updateIfStatusMatches($researchRunId, $expectedStatuses, [
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
        ]);
    }


    public function find(int $researchRunId): ?ResearchRun
    {
        return $this->researchRunRepository->find($researchRunId);
    }

}

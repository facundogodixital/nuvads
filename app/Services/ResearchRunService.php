<?php

namespace App\Services;

use Throwable;
use App\Models\Brand;
use App\Models\ResearchRun;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $type = $attributes['type'];
        $activeResearchRun = $this->findOneActiveForBrand($brand, $type);
        if ($activeResearchRun !== null) {
            throw new ApiException(409, 'research_already_running', 'Ya hay una investigación en curso.');
        }
        $isWebsiteMissing = $type === 'website' && $brand->website_url === null;
        if ($isWebsiteMissing) {
            throw new ApiException(422, 'website_missing', 'Guarda el sitio web antes de analizarlo.');
        }
        $isInstagramMissing = $type === 'instagram' && $brand->instagram_username === null;
        if ($isInstagramMissing) {
            throw new ApiException(422, 'instagram_missing', 'Guarda el usuario de Instagram antes de analizarlo.');
        }
        $isMetaAdsUrlMissing = $type === 'meta_ads' && $brand->meta_ads_url === null;
        if ($isMetaAdsUrlMissing) {
            throw new ApiException(
                422, 'meta_ads_url_missing', 'Guarda el enlace de tu página de Facebook antes de analizarla.',
            );
        }
        $isGoogleMapsUrlMissing = $type === 'google_reviews' && $brand->google_maps_url === null;
        if ($isGoogleMapsUrlMissing) {
            throw new ApiException(
                422, 'google_maps_url_missing', 'Guarda el enlace de tu negocio en Google Maps antes de analizarlo.',
            );
        }

        $input = match ($type) {
            'website' => [
                'url' => $brand->website_url,
                'model' => config('research.website.analysis_model'), // gpt-6-luna
            ],
            'instagram' => [
                'username' => $brand->instagram_username,
                'model' => config('research.instagram.analysis_model'), // gpt-6-luna
                'posts_limit' => config('research.instagram.posts_limit'),
            ],
            'meta_ads' => [
                'url' => $brand->meta_ads_url,
                'model' => config('research.meta_ads.analysis_model'), // gpt-6-luna
                'ads_limit' => config('research.meta_ads.ads_limit'),
            ],
            'google_reviews' => [
                'url' => $brand->google_maps_url,
                'model' => config('research.google_reviews.analysis_model'), // gpt-6-luna
                'reviews_limit' => config('research.google_reviews.reviews_limit'),
            ],
            'whatsapp_conversations' => [
                // El zip queda en el disco local hasta que el job lo lee y lo borra.
                'zip_path' => $attributes['zip_file']->store('whatsapp-conversations', 'local'),
                'model' => config('research.whatsapp_conversations.analysis_model'), // gpt-6-luna
                'conversations_limit' => config('research.whatsapp_conversations.conversations_limit'),
            ],
            'uploaded_files' => [
                'model' => config('research.uploaded_files.analysis_model'), // gpt-6-luna
                // Se completa en la transacción con las fuentes de los archivos subidos. Queda vacío en el nuevo
                // análisis que sigue a un borrado.
                'uploaded_knowledge_source_ids' => [],
            ],
        };

        // Las fuentes de los archivos subidos, para borrar sus archivos si no se puede crear la ejecución.
        $uploadedKnowledgeSources = collect();
        DB::beginTransaction();
        try {
            foreach ($attributes['files'] ?? [] as $uploadedFile) {
                $uploadedKnowledgeSources->push(resolve(UploadedFileService::class)->create($brand, $uploadedFile));
            }
            $hasUploadedFiles = $uploadedKnowledgeSources->isNotEmpty();
            if ($hasUploadedFiles) {
                $input['uploaded_knowledge_source_ids'] = $uploadedKnowledgeSources->pluck('id')->all();
            }
            $researchRun = $this->researchRunRepository->create($brand, [
                'type' => $type,
                'input' => $input,
                'status' => 'pending',
                'knowledge_source_ids' => [],
            ]);
            // La queue database comparte la transacción: la ejecución y su job se guardan juntos.
            $researchDispatcherService = resolve(ResearchDispatcherService::class);
            match ($type) {
                'website' => $researchDispatcherService->dispatchResearchWebsiteJob($researchRun->id),
                'instagram' => $researchDispatcherService->dispatchResearchInstagramJob($researchRun->id),
                'meta_ads' => $researchDispatcherService->dispatchResearchMetaAdsJob($researchRun->id),
                'google_reviews' => $researchDispatcherService->dispatchResearchGoogleReviewsJob($researchRun->id),
                'whatsapp_conversations' => $researchDispatcherService->dispatchResearchWhatsAppConversationsJob(
                    $researchRun->id,
                ),
                'uploaded_files' => $researchDispatcherService->dispatchResearchUploadedFilesJob($researchRun->id),
            };
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            // Sin ejecución, ningún job borraría el zip, que trae también los chats personales.
            $hasStoredZip = isset($input['zip_path']);
            if ($hasStoredZip) {
                Storage::disk('local')->delete($input['zip_path']);
            }
            // Las fuentes de los archivos subidos se deshicieron con la transacción; sus archivos se borran acá.
            foreach ($uploadedKnowledgeSources as $knowledgeSource) {
                Storage::disk('local')->delete($knowledgeSource->s3_path);
            }
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


    public function getWebsiteResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'website'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'website'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'website'),
        ];
    }


    public function getInstagramResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'instagram'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'instagram'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'instagram'),
        ];
    }


    public function getMetaAdsResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'meta_ads'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'meta_ads'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'meta_ads'),
        ];
    }


    public function getGoogleReviewsResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'google_reviews'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'google_reviews'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'google_reviews'),
        ];
    }


    public function getWhatsAppConversationsResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'whatsapp_conversations'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'whatsapp_conversations'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand(
                $brand, 'whatsapp_conversations',
            ),
        ];
    }


    public function getUploadedFilesResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'uploaded_files'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'uploaded_files'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'uploaded_files'),
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

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
            'audio' => [
                // El audio queda en el disco local hasta que el job lo transcribe y lo borra.
                'audio_path' => $attributes['audio_file']->store('audios', 'local'),
                'model' => config('research.audio.analysis_model'), // gpt-6-luna
                'transcription_model' => config('research.audio.transcription_model'), // gpt-transcribe
            ],
        };

        DB::beginTransaction();
        try {
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
                'audio' => $researchDispatcherService->dispatchResearchAudioJob($researchRun->id),
            };
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            // Sin ejecución, ningún job borraría el archivo subido: el zip trae también los chats personales, y el
            // audio no se guarda.
            $uploadedFilePath = $input['zip_path'] ?? $input['audio_path'] ?? null;
            $hasUploadedFile = $uploadedFilePath !== null;
            if ($hasUploadedFile) {
                Storage::disk('local')->delete($uploadedFilePath);
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


    public function getAudioResearchStatus(Brand $brand): array
    {
        return [
            'active' => $this->researchRunRepository->findOneActiveForBrand($brand, 'audio'),
            'latest' => $this->researchRunRepository->findOneLatestForBrand($brand, 'audio'),
            'last_completed' => $this->researchRunRepository->findOneCompletedForBrand($brand, 'audio'),
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
            'status_message' => $message,
        ]);
    }

}

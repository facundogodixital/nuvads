<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\KnowledgeSource;
use App\Models\KnowledgeInsight;
use App\DTO\GoogleReviewsInsightsDto;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\WhatsAppConversationsInsightsDto;
use App\Repositories\KnowledgeInsightRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class KnowledgeInsightService
{

    private KnowledgeInsightRepository $knowledgeInsightRepository;


    public function __construct(KnowledgeInsightRepository $knowledgeInsightRepository)
    {
        $this->knowledgeInsightRepository = $knowledgeInsightRepository;
    }


    public function create(Brand $brand, array $attributes): KnowledgeInsight
    {
        $this->checkReferencesBelongToBrand($brand, $attributes);
        return $this->knowledgeInsightRepository->create($brand, $attributes);
    }


    public function update(Brand $brand, int $knowledgeInsightId, array $attributes): KnowledgeInsight
    {
        $this->checkReferencesBelongToBrand($brand, $attributes);
        return $this->knowledgeInsightRepository->update($brand, $knowledgeInsightId, $attributes);
    }


    public function find(Brand $brand, int $knowledgeInsightId): ?KnowledgeInsight
    {
        return $this->knowledgeInsightRepository->find($brand, $knowledgeInsightId);
    }


    public function list(Brand $brand): Collection
    {
        return $this->knowledgeInsightRepository->list($brand);
    }


    public function delete(Brand $brand, int $knowledgeInsightId): bool
    {
        return $this->knowledgeInsightRepository->delete($brand, $knowledgeInsightId);
    }


    // Conclusiones vigentes: las activas y las corregidas por el usuario (superseded).
    public function findCurrentByTypes(Brand $brand, array $types): Collection
    {
        return $this->knowledgeInsightRepository->findByTypesAndStatuses($brand, $types, ['active', 'superseded']);
    }


    // Lo que muestra la pantalla del sitio web: analysis, el análisis vigente con el resumen o null, e insights, las
    // conclusiones vigentes.
    public function getWebsiteInsights(Brand $brand): array
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, ['website_brand_analysis', 'website_insight']);

        return [
            'analysis' => $knowledgeInsights->firstWhere('type', 'website_brand_analysis'),
            'insights' => $knowledgeInsights->where('type', 'website_insight')->values(),
        ];
    }


    // Lo que muestra la pantalla de Instagram: analysis, el análisis vigente con el resumen y las métricas en su
    // payload o null; insights, las conclusiones vigentes; y posts, los posteos que leyó ese análisis.
    public function getInstagramInsights(Brand $brand): array
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, ['instagram_analysis', 'instagram_insight']);
        $analysis = $knowledgeInsights->firstWhere('type', 'instagram_analysis');
        $postIds = $analysis?->knowledge_source_ids ?? [];

        return [
            'analysis' => $analysis,
            'insights' => $knowledgeInsights->where('type', 'instagram_insight')->values(),
            'posts' => resolve(KnowledgeSourceService::class)->findByIds($brand, $postIds),
        ];
    }


    // Lo que muestra la pantalla de los anuncios de Meta: analysis, el análisis vigente con el resumen y las métricas
    // en su payload o null; insights, las conclusiones vigentes; y ads, los anuncios que leyó ese análisis.
    public function getMetaAdsInsights(Brand $brand): array
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, ['meta_ads_analysis', 'meta_ads_insight']);
        $analysis = $knowledgeInsights->firstWhere('type', 'meta_ads_analysis');
        $adIds = $analysis?->knowledge_source_ids ?? [];

        return [
            'analysis' => $analysis,
            'ads' => resolve(KnowledgeSourceService::class)->findByIds($brand, $adIds),
            'insights' => $knowledgeInsights->where('type', 'meta_ads_insight')->values(),
        ];
    }


    // Lo que muestra la pantalla de las reseñas de Google: las filas vigentes y las reseñas que destacan.
    public function getGoogleReviewsInsights(Brand $brand): GoogleReviewsInsightsDto
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, [
            'google_reviews_pain',
            'google_reviews_metrics',
            'google_reviews_insight',
            'google_reviews_strength',
            'google_reviews_brand_analysis',
        ]);
        $pains = $knowledgeInsights->where('type', 'google_reviews_pain')->values();
        $insights = $knowledgeInsights->where('type', 'google_reviews_insight')->values();
        $strengths = $knowledgeInsights->where('type', 'google_reviews_strength')->values();
        $highlightedKnowledgeSourceIds = $pains->concat($strengths)->concat($insights)
            ->flatMap(fn (KnowledgeInsight $knowledgeInsight): array => $knowledgeInsight->payload['highlight_ids'])
            ->unique()
            ->values()
            ->all();

        return new GoogleReviewsInsightsDto(
            metrics: $knowledgeInsights->firstWhere('type', 'google_reviews_metrics'),
            analysis: $knowledgeInsights->firstWhere('type', 'google_reviews_brand_analysis'),
            pains: $pains,
            strengths: $strengths,
            insights: $insights,
            reviews: resolve(KnowledgeSourceService::class)->findByIds($brand, $highlightedKnowledgeSourceIds),
        );
    }


    // Lo que muestra la pantalla de las conversaciones de WhatsApp: las filas vigentes y las conversaciones que
    // destacan.
    public function getWhatsAppConversationsInsights(Brand $brand): WhatsAppConversationsInsightsDto
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, [
            'whatsapp_conversations_metrics',
            'whatsapp_conversations_insight',
            'whatsapp_conversations_question',
            'whatsapp_conversations_objection',
            'whatsapp_conversations_brand_analysis',
        ]);
        $insights = $knowledgeInsights->where('type', 'whatsapp_conversations_insight')->values();
        $questions = $knowledgeInsights->where('type', 'whatsapp_conversations_question')->values();
        $objections = $knowledgeInsights->where('type', 'whatsapp_conversations_objection')->values();
        $highlightedKnowledgeSourceIds = $questions->concat($objections)->concat($insights)
            ->flatMap(fn (KnowledgeInsight $knowledgeInsight): array => $knowledgeInsight->payload['highlight_ids'])
            ->unique()
            ->values()
            ->all();

        return new WhatsAppConversationsInsightsDto(
            metrics: $knowledgeInsights->firstWhere('type', 'whatsapp_conversations_metrics'),
            analysis: $knowledgeInsights->firstWhere('type', 'whatsapp_conversations_brand_analysis'),
            questions: $questions,
            objections: $objections,
            insights: $insights,
            conversations: resolve(KnowledgeSourceService::class)->findByIds($brand, $highlightedKnowledgeSourceIds),
        );
    }


    // Lo que muestra la pantalla del audio: analysis, el análisis vigente con el resumen o null; insights, las
    // conclusiones vigentes; y audio, la fuente con la transcripción que leyó ese análisis, o null.
    public function getAudioInsights(Brand $brand): array
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, ['audio_analysis', 'audio_insight']);
        $analysis = $knowledgeInsights->firstWhere('type', 'audio_analysis');
        $audioIds = $analysis?->knowledge_source_ids ?? [];

        return [
            'analysis' => $analysis,
            'insights' => $knowledgeInsights->where('type', 'audio_insight')->values(),
            'audio' => resolve(KnowledgeSourceService::class)->findByIds($brand, $audioIds)->first(),
        ];
    }


    // Lo que muestra la pantalla de las fotos y los documentos: analysis, el análisis vigente con el resumen o null;
    // insights, las conclusiones vigentes; y files, todos los archivos subidos, también los que se están analizando o
    // no se pudieron leer, cada uno con su enlace.
    public function getUploadedFilesInsights(Brand $brand): array
    {
        $knowledgeInsights = $this->findCurrentByTypes($brand, ['uploaded_files_analysis', 'uploaded_files_insight']);

        return [
            'analysis' => $knowledgeInsights->firstWhere('type', 'uploaded_files_analysis'),
            'insights' => $knowledgeInsights->where('type', 'uploaded_files_insight')->values(),
            'files' => resolve(UploadedFileService::class)->list($brand),
        ];
    }


    // Las conclusiones activas de un tipo pasan a outdated; las corregidas o rechazadas no se tocan.
    public function outdateActiveByType(Brand $brand, string $type): int
    {
        return $this->knowledgeInsightRepository->updateStatusByTypeAndStatus($brand, $type, 'active', 'outdated');
    }


    private function checkReferencesBelongToBrand(Brand $brand, array $attributes): void
    {
        $knowledgeSourceIds = $attributes['knowledge_source_ids'] ?? [];
        $knowledgeSources = resolve(KnowledgeSourceService::class)->findByIds($brand, $knowledgeSourceIds);
        $foreignKnowledgeSourceIds = array_values(array_diff($knowledgeSourceIds, $knowledgeSources->modelKeys()));
        if ($foreignKnowledgeSourceIds !== []) {
            throw (new ModelNotFoundException())->setModel(KnowledgeSource::class, $foreignKnowledgeSourceIds);
        }

        $parentInsightIds = $attributes['parent_insight_ids'] ?? [];
        foreach ($parentInsightIds as $parentInsightId) {
            $parentInsight = $this->knowledgeInsightRepository->find($brand, $parentInsightId);
            if ($parentInsight === null) {
                throw (new ModelNotFoundException())->setModel(KnowledgeInsight::class, [$parentInsightId]);
            }
        }
    }

}

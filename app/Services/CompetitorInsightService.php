<?php

namespace App\Services;

use App\Models\Competitor;
use App\Models\CompetitorInsight;
use Illuminate\Database\Eloquent\Collection;
use App\DTO\CompetitorGoogleReviewsInsightsDto;
use App\Repositories\CompetitorInsightRepository;


class CompetitorInsightService
{

    private CompetitorInsightRepository $competitorInsightRepository;


    public function __construct(CompetitorInsightRepository $competitorInsightRepository)
    {
        $this->competitorInsightRepository = $competitorInsightRepository;
    }


    public function create(Competitor $competitor, array $attributes): CompetitorInsight
    {
        return $this->competitorInsightRepository->create($competitor, $attributes);
    }


    // Conclusiones vigentes: las activas y las corregidas por el usuario (superseded).
    public function findCurrentByTypes(Competitor $competitor, array $types): Collection
    {
        return $this->competitorInsightRepository->findByTypesAndStatuses(
            $competitor, $types, ['active', 'superseded'],
        );
    }


    // Lo que muestra la pantalla del sitio web: analysis, el análisis vigente con el resumen o null, e insights, las
    // conclusiones vigentes.
    public function getWebsiteInsights(Competitor $competitor): array
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, ['website_analysis', 'website_insight']);

        return [
            'analysis' => $competitorInsights->firstWhere('type', 'website_analysis'),
            'insights' => $competitorInsights->where('type', 'website_insight')->values(),
        ];
    }


    // Lo que muestra la pantalla de Instagram: analysis, el análisis vigente con el resumen y las métricas en su
    // payload o null; insights, las conclusiones vigentes; y posts, los posteos que leyó ese análisis.
    public function getInstagramInsights(Competitor $competitor): array
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, ['instagram_analysis', 'instagram_insight']);
        $analysis = $competitorInsights->firstWhere('type', 'instagram_analysis');
        $postIds = $analysis?->competitor_source_ids ?? [];

        return [
            'analysis' => $analysis,
            'insights' => $competitorInsights->where('type', 'instagram_insight')->values(),
            'posts' => resolve(CompetitorSourceService::class)->findByIds($competitor, $postIds),
        ];
    }


    // Lo que muestra la pantalla de los anuncios de Meta: analysis, el análisis vigente con el resumen y las métricas
    // en su payload o null; insights, las conclusiones vigentes; y ads, los anuncios que leyó ese análisis.
    public function getMetaAdsInsights(Competitor $competitor): array
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, ['meta_ads_analysis', 'meta_ads_insight']);
        $analysis = $competitorInsights->firstWhere('type', 'meta_ads_analysis');
        $adIds = $analysis?->competitor_source_ids ?? [];

        return [
            'analysis' => $analysis,
            'insights' => $competitorInsights->where('type', 'meta_ads_insight')->values(),
            'ads' => resolve(CompetitorSourceService::class)->findByIds($competitor, $adIds),
        ];
    }


    // Lo que muestra la pantalla de las reseñas de Google: las filas vigentes y las reseñas que destacan.
    public function getGoogleReviewsInsights(Competitor $competitor): CompetitorGoogleReviewsInsightsDto
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, [
            'google_reviews_pain',
            'google_reviews_insight',
            'google_reviews_analysis',
            'google_reviews_strength',
        ]);
        $pains = $competitorInsights->where('type', 'google_reviews_pain')->values();
        $insights = $competitorInsights->where('type', 'google_reviews_insight')->values();
        $strengths = $competitorInsights->where('type', 'google_reviews_strength')->values();
        $highlightedReviewIds = $pains->concat($strengths)
            ->flatMap(fn (CompetitorInsight $competitorInsight): array => $competitorInsight->payload['highlight_ids'])
            ->unique()
            ->values()
            ->all();

        return new CompetitorGoogleReviewsInsightsDto(
            analysis: $competitorInsights->firstWhere('type', 'google_reviews_analysis'),
            pains: $pains,
            strengths: $strengths,
            insights: $insights,
            reviews: resolve(CompetitorSourceService::class)->findByIds($competitor, $highlightedReviewIds),
        );
    }


    // Las conclusiones activas de un tipo pasan a outdated; las corregidas o rechazadas no se tocan.
    public function outdateActiveByType(Competitor $competitor, string $type): int
    {
        return $this->competitorInsightRepository->updateStatusByTypeAndStatus(
            $competitor, $type, 'active', 'outdated',
        );
    }

}

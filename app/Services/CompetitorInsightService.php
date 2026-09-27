<?php

namespace App\Services;

use App\Helpers\S3Helper;
use App\Models\Competitor;
use App\Models\CompetitorSource;
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
    // payload o null; insights, las conclusiones vigentes; y posts, los posteos que leyó ese análisis, con los enlaces
    // de sus archivos.
    public function getInstagramInsights(Competitor $competitor): array
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, ['instagram_analysis', 'instagram_insight']);
        $analysis = $competitorInsights->firstWhere('type', 'instagram_analysis');
        $postIds = $analysis?->competitor_source_ids ?? [];
        $posts = resolve(CompetitorSourceService::class)->findByIds($competitor, $postIds);

        return [
            'analysis' => $analysis,
            'insights' => $competitorInsights->where('type', 'instagram_insight')->values(),
            'posts' => $this->addPostsMediaUrls($posts),
        ];
    }


    // Lo que muestra la pantalla de los anuncios de Meta: analysis, el análisis vigente con el resumen y las métricas
    // en su payload o null; insights, las conclusiones vigentes; y ads, los anuncios que leyó ese análisis, con los
    // enlaces de sus archivos.
    public function getMetaAdsInsights(Competitor $competitor): array
    {
        $competitorInsights = $this->findCurrentByTypes($competitor, ['meta_ads_analysis', 'meta_ads_insight']);
        $analysis = $competitorInsights->firstWhere('type', 'meta_ads_analysis');
        $adIds = $analysis?->competitor_source_ids ?? [];
        $ads = resolve(CompetitorSourceService::class)->findByIds($competitor, $adIds);

        return [
            'analysis' => $analysis,
            'insights' => $competitorInsights->where('type', 'meta_ads_insight')->values(),
            'ads' => $this->addAdsMediaUrls($ads),
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


    // Suma a cada posteo image_urls y video_urls, los enlaces temporales de S3 de sus imágenes y su video, en el mismo
    // orden. No son columnas: solo viajan en la respuesta.
    private function addPostsMediaUrls(Collection $posts): Collection
    {
        $s3Helper = resolve(S3Helper::class);

        return $posts->each(function (CompetitorSource $post) use ($s3Helper): void {
            $imageUrls = array_map($s3Helper->getTemporaryUrl(...), $post->payload['image_s3_paths']);
            $videoUrls = array_map($s3Helper->getTemporaryUrl(...), $post->payload['video_s3_paths']);
            $post->setAttribute('image_urls', $imageUrls);
            $post->setAttribute('video_urls', $videoUrls);
        });
    }


    // Suma a cada anuncio media_urls: por cada elemento de media, image_url y video_url, los enlaces temporales de S3
    // de su imagen y su video. No son columnas: solo viajan en la respuesta.
    private function addAdsMediaUrls(Collection $ads): Collection
    {
        $s3Helper = resolve(S3Helper::class);

        return $ads->each(function (CompetitorSource $ad) use ($s3Helper): void {
            $mediaUrls = array_map(fn (array $mediaItem): array => [
                'image_url' => $s3Helper->getTemporaryUrl($mediaItem['image_s3_path']),
                'video_url' => $s3Helper->getTemporaryUrl($mediaItem['video_s3_path']),
            ], $ad->payload['media']);
            $ad->setAttribute('media_urls', $mediaUrls);
        });
    }

}

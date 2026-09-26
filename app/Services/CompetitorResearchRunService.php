<?php

namespace App\Services;

use Throwable;
use App\Models\Competitor;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use App\Models\CompetitorResearchRun;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\CompetitorResearchRunRepository;
use App\Services\Dispatchers\ResearchDispatcherService;


class CompetitorResearchRunService
{

    // Qué se investiga de un competidor y en qué campo guarda su enlace.
    const array LINK_FIELDS_BY_TYPE = [
        'website' => 'website_url',
        'meta_ads' => 'meta_ads_url',
        'instagram' => 'instagram_username',
        'google_reviews' => 'google_maps_url',
    ];

    private CompetitorResearchRunRepository $competitorResearchRunRepository;


    public function __construct(CompetitorResearchRunRepository $competitorResearchRunRepository)
    {
        $this->competitorResearchRunRepository = $competitorResearchRunRepository;
    }


    // Solo puede haber una investigación activa por competidor y fuente, y la fuente necesita su enlace guardado. La
    // entrada sale del competidor y de config/research.php, y queda congelada en la investigación.
    public function create(Competitor $competitor, string $type): CompetitorResearchRun
    {
        $activeResearchRun = $this->competitorResearchRunRepository->findOneActiveForCompetitor($competitor, $type);
        if ($activeResearchRun !== null) {
            $activeResearchMessages = [
                'website' => 'Ya hay un análisis del sitio web de este competidor en curso.',
                'instagram' => 'Ya hay un análisis del Instagram de este competidor en curso.',
                'meta_ads' => 'Ya hay un análisis de los anuncios de este competidor en curso.',
                'google_reviews' => 'Ya hay un análisis de las reseñas de este competidor en curso.',
            ];
            throw new ApiException(409, 'research_already_running', $activeResearchMessages[$type]);
        }
        $sourceLink = $competitor->{self::LINK_FIELDS_BY_TYPE[$type]};
        if ($sourceLink === null) {
            $missingLinkMessages = [
                'website' => 'Guarda el sitio web del competidor antes de analizarlo.',
                'instagram' => 'Guarda el usuario de Instagram del competidor antes de analizarlo.',
                'meta_ads' => 'Guarda el enlace de la página de Facebook del competidor antes de analizarla.',
                'google_reviews' => 'Guarda el enlace del competidor en Google Maps antes de analizarlo.',
            ];
            throw new ApiException(422, 'competitor_link_missing', $missingLinkMessages[$type]);
        }

        $input = match ($type) {
            'website' => [
                'url' => $sourceLink,
                'model' => config('research.competitors.website.analysis_model'), // gpt-6-luna
            ],
            'instagram' => [
                'username' => $sourceLink,
                'model' => config('research.competitors.instagram.analysis_model'), // gpt-6-luna
                'posts_limit' => config('research.competitors.instagram.posts_limit'),
            ],
            'meta_ads' => [
                'url' => $sourceLink,
                'model' => config('research.competitors.meta_ads.analysis_model'), // gpt-6-luna
                'ads_limit' => config('research.competitors.meta_ads.ads_limit'),
            ],
            'google_reviews' => [
                'url' => $sourceLink,
                'model' => config('research.competitors.google_reviews.analysis_model'), // gpt-6-luna
                'reviews_limit' => config('research.competitors.google_reviews.reviews_limit'),
            ],
        };

        DB::beginTransaction();
        try {
            $researchRun = $this->competitorResearchRunRepository->create($competitor, [
                'type' => $type,
                'input' => $input,
                'status' => 'pending',
                'competitor_source_ids' => [],
            ]);
            // La queue database comparte la transacción: la investigación y su job se guardan juntos.
            $researchDispatcherService = resolve(ResearchDispatcherService::class);
            match ($type) {
                'website' => $researchDispatcherService->dispatchResearchCompetitorWebsiteJob($researchRun->id),
                'instagram' => $researchDispatcherService->dispatchResearchCompetitorInstagramJob($researchRun->id),
                'meta_ads' => $researchDispatcherService->dispatchResearchCompetitorMetaAdsJob($researchRun->id),
                'google_reviews' => $researchDispatcherService->dispatchResearchCompetitorGoogleReviewsJob(
                    $researchRun->id,
                ),
            };
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $researchRun;
    }


    public function update(CompetitorResearchRun $researchRun, array $attributes): CompetitorResearchRun
    {
        return $this->competitorResearchRunRepository->update($researchRun, $attributes);
    }


    public function find(int $researchRunId): ?CompetitorResearchRun
    {
        return $this->competitorResearchRunRepository->find($researchRunId);
    }


    // active: la investigación en curso; latest: la última; last_completed: la última que terminó bien. Cada una o
    // null.
    public function getResearchStatus(Competitor $competitor, string $type): array
    {
        return [
            'active' => $this->competitorResearchRunRepository->findOneActiveForCompetitor($competitor, $type),
            'latest' => $this->competitorResearchRunRepository->findOneLatestForCompetitor($competitor, $type),
            'last_completed' => $this->competitorResearchRunRepository->findOneCompletedForCompetitor(
                $competitor, $type,
            ),
        ];
    }


    // El estado de las cuatro fuentes del competidor, por tipo: website, instagram, meta_ads y google_reviews.
    public function getResearchStatusesByType(Competitor $competitor): array
    {
        $researchStatusesByType = [];
        foreach (array_keys(self::LINK_FIELDS_BY_TYPE) as $type) {
            $researchStatusesByType[$type] = $this->getResearchStatus($competitor, $type);
        }

        return $researchStatusesByType;
    }


    // El estado de las cuatro fuentes de cada competidor, por ID del competidor y por tipo.
    public function getResearchStatusesByCompetitorId(Collection $competitors): array
    {
        $researchStatusesByCompetitorId = [];
        foreach ($competitors as $competitor) {
            $researchStatusesByCompetitorId[$competitor->id] = $this->getResearchStatusesByType($competitor);
        }

        return $researchStatusesByCompetitorId;
    }


    // Cada investigación que termina bien cambia lo que sabemos del competidor, así que la marca vuelve a calcular lo
    // que sabe de su competencia.
    public function complete(CompetitorResearchRun $researchRun): CompetitorResearchRun
    {
        DB::beginTransaction();
        try {
            $researchRun = $this->competitorResearchRunRepository->update($researchRun, [
                'status' => 'completed',
                'finished_at' => now(),
            ]);
            // La queue database comparte la transacción: el cierre y su job se guardan juntos.
            resolve(ResearchDispatcherService::class)->dispatchResearchBrandCompetitionJob(
                $researchRun->competitor->brand_id,
            );
            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $researchRun;
    }


    public function fail(int $researchRunId, string $message): ?CompetitorResearchRun
    {
        $researchRun = $this->competitorResearchRunRepository->find($researchRunId);
        if ($researchRun === null) {
            return null;
        }

        return $this->competitorResearchRunRepository->update($researchRun, [
            'status' => 'failed',
            'finished_at' => now(),
            'status_message' => $message,
        ]);
    }

}

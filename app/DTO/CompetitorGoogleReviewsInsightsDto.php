<?php

namespace App\DTO;

use App\Models\CompetitorSource;
use App\Models\CompetitorInsight;
use Illuminate\Database\Eloquent\Collection;


class CompetitorGoogleReviewsInsightsDto
{


    /**
     * Lo que muestra la pantalla de las reseñas de Google de un competidor. analysis es el análisis vigente, con las
     * métricas en su payload, o null si todavía no hay; pains, strengths e insights, las quejas, los elogios y las
     * conclusiones vigentes; y reviews, solo las reseñas destacadas de las quejas y los elogios (highlight_ids en su
     * payload). Las propiedades se llaman como las claves del JSON que recibe la pantalla.
     *
     * @param  Collection<int, CompetitorInsight>  $pains
     * @param  Collection<int, CompetitorInsight>  $strengths
     * @param  Collection<int, CompetitorInsight>  $insights
     * @param  Collection<int, CompetitorSource>  $reviews
     */
    public function __construct(
        public readonly ?CompetitorInsight $analysis,
        public readonly Collection $pains,
        public readonly Collection $strengths,
        public readonly Collection $insights,
        public readonly Collection $reviews,
    ) {}

}

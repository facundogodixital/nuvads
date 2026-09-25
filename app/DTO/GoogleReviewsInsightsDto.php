<?php

namespace App\DTO;

use App\Models\KnowledgeSource;
use App\Models\KnowledgeInsight;
use Illuminate\Database\Eloquent\Collection;


class GoogleReviewsInsightsDto
{


    /**
     * Lo que muestra la pantalla de las reseñas de Google. metrics y analysis son las métricas y el análisis
     * vigentes, o null si todavía no hay; pains, strengths e insights, las quejas, las fortalezas y las conclusiones
     * vigentes; y reviews, solo las reseñas destacadas de esas filas (highlight_ids en su payload). Las propiedades
     * se llaman como las claves del JSON que recibe la pantalla.
     *
     * @param  Collection<int, KnowledgeInsight>  $pains
     * @param  Collection<int, KnowledgeInsight>  $strengths
     * @param  Collection<int, KnowledgeInsight>  $insights
     * @param  Collection<int, KnowledgeSource>  $reviews
     */
    public function __construct(
        public readonly ?KnowledgeInsight $metrics,
        public readonly ?KnowledgeInsight $analysis,
        public readonly Collection $pains,
        public readonly Collection $strengths,
        public readonly Collection $insights,
        public readonly Collection $reviews,
    ) {}

}

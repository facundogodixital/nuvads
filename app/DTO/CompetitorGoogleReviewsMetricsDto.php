<?php

namespace App\DTO;

class CompetitorGoogleReviewsMetricsDto
{


    /**
     * Métricas de las reseñas leídas de un competidor, calculadas en PHP. googleTotalScore y googleReviewsCount son lo
     * que muestra su ficha de Google.
     *
     * @param  array<int, int>  $starsDistribution  cantidad de reseñas por estrellas, de 1 a 5
     */
    public function __construct(
        public readonly ?float $googleTotalScore,
        public readonly ?int $googleReviewsCount,
        public readonly int $reviewsCount,
        public readonly int $withTextCount,
        public readonly ?float $averageStars,
        public readonly array $starsDistribution,
        public readonly ?float $ownerResponseRate,
        public readonly ?float $medianOwnerResponseDays,
    ) {}


    // La forma en que se guarda en el payload y se le manda al modelo.
    public function toArray(): array
    {
        return [
            'google_total_score' => $this->googleTotalScore,
            'google_reviews_count' => $this->googleReviewsCount,
            'reviews_count' => $this->reviewsCount,
            'with_text_count' => $this->withTextCount,
            'average_stars' => $this->averageStars,
            'stars_distribution' => $this->starsDistribution,
            'owner_response_rate' => $this->ownerResponseRate,
            'median_owner_response_days' => $this->medianOwnerResponseDays,
        ];
    }

}

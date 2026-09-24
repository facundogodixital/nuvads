<?php

namespace App\DTO;

class GoogleReviewsMetricsDto
{


    /**
     * Métricas de las reseñas leídas, calculadas en PHP. googleTotalScore y googleReviewsCount son lo que muestra la
     * ficha de Google. Las fechas van como Y-m-d.
     *
     * @param  array<int, int>  $starsDistribution  cantidad de reseñas por estrellas, de 1 a 5
     * @param  array<string, int>  $reviewsPerMonth  los últimos 12 meses, por ejemplo '2026-09' => 32
     * @param  array<string, float>  $detailedRatingAverages  promedio por aspecto, por ejemplo 'Comida' => 4.6
     * @param  list<array{from: string, to: string, reviews_count: int, average_stars: ?float}>  $timeRanges
     */
    public function __construct(
        public readonly ?float $googleTotalScore,
        public readonly ?int $googleReviewsCount,
        public readonly int $reviewsCount,
        public readonly int $withTextCount,
        public readonly ?float $averageStars,
        public readonly array $starsDistribution,
        public readonly array $reviewsPerMonth,
        public readonly ?float $ownerResponseRate,
        public readonly ?float $medianOwnerResponseDays,
        public readonly array $detailedRatingAverages,
        public readonly ?string $oldestReviewAt,
        public readonly ?string $newestReviewAt,
        public readonly array $timeRanges,
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
            'reviews_per_month' => $this->reviewsPerMonth,
            'owner_response_rate' => $this->ownerResponseRate,
            'median_owner_response_days' => $this->medianOwnerResponseDays,
            'detailed_rating_averages' => $this->detailedRatingAverages,
            'oldest_review_at' => $this->oldestReviewAt,
            'newest_review_at' => $this->newestReviewAt,
            'time_ranges' => $this->timeRanges,
        ];
    }

}

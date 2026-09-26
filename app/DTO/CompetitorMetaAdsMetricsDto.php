<?php

namespace App\DTO;

class CompetitorMetaAdsMetricsDto
{


    /**
     * Métricas de los anuncios leídos de un competidor, calculadas en PHP. longestRunningDays son los días del anuncio
     * que más lleva corriendo.
     *
     * @param  array<string, array{ads: int, average_days_running: int}>  $formats  por formato, como 'image'
     * @param  array<string, int>  $platforms  cuántos anuncios salen en cada plataforma, como 'instagram' => 3
     */
    public function __construct(
        public readonly int $adsCount,
        public readonly int $longestRunningDays,
        public readonly array $formats,
        public readonly array $platforms,
    ) {}


    // La forma en que se guarda en el payload y se le manda al modelo.
    public function toArray(): array
    {
        return [
            'ads_count' => $this->adsCount,
            'longest_running_days' => $this->longestRunningDays,
            'formats' => $this->formats,
            'platforms' => $this->platforms,
        ];
    }

}

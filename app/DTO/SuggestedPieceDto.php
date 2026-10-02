<?php

namespace App\DTO;

class SuggestedPieceDto
{


    /**
     * La pieza sugerida de una idea: lo que escribió el modelo y todavía no se guardó. carouselScript son las reseñas
     * del carrusel, una por placa en el orden de la idea y sin la placa final, o null si la idea tiene una sola
     * reseña; singleScript, la placa única, con la reseña más fuerte; closingTexts, las opciones para la placa final
     * del carrusel, vacías sin carrusel; y copies, las opciones para el texto del posteo. El text de cada reseña es el
     * que va en la imagen: entero o cortado con puntos suspensivos.
     *
     * @param  list<IdeaReviewDto>|null  $carouselScript
     * @param  list<IdeaReviewDto>  $singleScript
     * @param  list<string>  $closingTexts
     * @param  list<string>  $copies
     */
    public function __construct(
        public readonly int $ideaId,
        public readonly ?array $carouselScript,
        public readonly array $singleScript,
        public readonly array $closingTexts,
        public readonly array $copies,
    ) {}


    // La forma en que viaja en la respuesta. Cada reseña viaja con las claves de IdeaReviewDto.
    public function toArray(): array
    {
        return [
            'idea_id' => $this->ideaId,
            'carousel_script' => $this->carouselScript,
            'single_script' => $this->singleScript,
            'closing_texts' => $this->closingTexts,
            'copies' => $this->copies,
        ];
    }

}

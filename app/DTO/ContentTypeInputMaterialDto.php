<?php

namespace App\DTO;

class ContentTypeInputMaterialDto
{


    /**
     * El material que manda una entrada del cerebro para proponer ideas. promptText es su bloque del prompt: una línea
     * que dice qué es y sus datos en JSON. knowledgeInsightIds y knowledgeSourceIds son las conclusiones y las fuentes
     * cuyos IDs mandó: de lo que devuelve el modelo quedan solo esos. ideaReviews son las reseñas que mandó, por ID de
     * su fuente, para mostrarlas junto a cada idea sugerida.
     *
     * @param  list<int>  $knowledgeInsightIds
     * @param  list<int>  $knowledgeSourceIds
     * @param  array<int, IdeaReviewDto>  $ideaReviews
     */
    public function __construct(
        public readonly string $promptText,
        public readonly array $knowledgeInsightIds,
        public readonly array $knowledgeSourceIds,
        public readonly array $ideaReviews,
    ) {}

}

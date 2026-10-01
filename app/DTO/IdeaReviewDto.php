<?php

namespace App\DTO;

class IdeaReviewDto
{


    /**
     * Una reseña de Google tal como viaja al proponer ideas: al modelo, en el material de una entrada, y a la pantalla,
     * junto a cada idea sugerida. id es el de su fuente en knowledge_sources; name, el nombre de pila de quien la
     * escribió, sin apellido, o null si no lo tiene; date, cuándo se publicó (Y-m-d); y text, lo que escribió, cortado
     * si es largo. Las propiedades se llaman como las claves del JSON.
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly int $stars,
        public readonly string $date,
        public readonly string $text,
    ) {}

}

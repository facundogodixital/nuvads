<?php

namespace App\DTO;

class IdeaReviewDto
{


    /**
     * Una reseña de Google de una idea, tal como viaja al modelo y a la pantalla. Al proponer ideas va en el material
     * de una entrada y junto a cada idea sugerida, con text cortado si es largo. Al escribir la pieza va entera al
     * modelo, y en el guión de la pieza sugerida va con text tal como va en la imagen: entero o cortado con puntos
     * suspensivos. id es el de su fuente en knowledge_sources; name, el nombre de pila de quien la escribió, sin
     * apellido, o null si no lo tiene; date, cuándo se publicó (Y-m-d); y text, lo que escribió. Las propiedades se
     * llaman como las claves del JSON.
     */
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly int $stars,
        public readonly string $date,
        public readonly string $text,
    ) {}

}

<?php

namespace App\DTO;

class HomepageReviewDto
{


    /**
     * additionalUrls: hasta dos enlaces internos de la portada elegidos para leer.
     * visualBrandFields: solo los campos visuales de la marca que se le pidieron al modelo.
     *
     * @param  list<string>  $additionalUrls
     * @param  array<string, mixed>  $visualBrandFields
     */
    public function __construct(
        public readonly array $additionalUrls,
        public readonly array $visualBrandFields,
    ) {}

}

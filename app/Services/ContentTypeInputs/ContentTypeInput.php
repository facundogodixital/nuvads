<?php

namespace App\Services\ContentTypeInputs;

use App\Models\Brand;
use App\DTO\ContentTypeInputMaterialDto;


// El contrato de una entrada del cerebro: lee su parte de lo que la app sabe de una marca y la devuelve como material
// para proponer ideas. Los tipos de contenido la nombran en inputs, y config/content.php dice qué clase la lee.
interface ContentTypeInput
{


    // Si la entrada alcanza por sí sola para sostener ideas. Las que no, como el puntaje, son datos de apoyo: viajan en
    // el material, pero no alcanzan para que el tipo se pueda usar.
    public function canSupportIdeas(): bool;


    // Si la marca no tiene nada que mandar en esta entrada.
    public function isEmpty(Brand $brand): bool;


    // Qué le falta a la marca para que la entrada tenga material, en un texto para el usuario.
    public function getMissingMaterialMessage(Brand $brand): string;


    // El material de la marca para el prompt, o null si no tiene nada que mandar.
    public function getMaterial(Brand $brand): ?ContentTypeInputMaterialDto;

}

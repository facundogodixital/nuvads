<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\ContentTypeService;


class ContentTypeSeeder extends Seeder
{


    // Crea cada tipo solo si no hay uno activo con su key; nunca actualiza ni borra los que ya existen.
    public function run(): void
    {
        // name y description los ve el usuario; instructions, angles y layouts son para el modelo, en voseo.
        $contentTypes = [
            [
                'key' => 'customer_reviews',
                'name' => 'Reseñas de clientes',
                'description' => 'Lo que ya dijeron de ti',
                'instructions' => 'Cada idea muestra de 1 a 3 reseñas de Google que elogian lo mismo. El título dice '
                    .'qué tienen en común, por ejemplo "Lo que más repiten: que te atienden bien". Usá solo reseñas de '
                    .'4 o 5 estrellas y con texto, y preferí las más recientes. Las palabras del cliente van tal cual: '
                    .'podés cortar una reseña larga con puntos suspensivos, pero nunca cambiar lo que dice. Cada '
                    .'reseña lleva sus estrellas y el nombre de pila de quien la escribió, sin apellido; como avatar, '
                    .'la inicial del nombre o un ícono, nunca una cara. Con una reseña, la pieza es una placa; con dos '
                    .'o tres, un carrusel con una reseña por placa y una placa final con una invitación acorde al '
                    .'negocio.',
                'inputs' => [
                    'google_reviews',
                    'google_review_strengths',
                    'google_review_products',
                    'google_review_staff',
                    'google_review_score',
                ],
                'angles' => null,
                'layouts' => [
                    'La reseña dentro de una píldora redondeada, con el avatar a un lado, el nombre y las estrellas '
                        .'arriba y el texto debajo.',
                    'La reseña en una tarjeta grande, con el nombre arriba y las estrellas debajo del texto.',
                    'La reseña como si fuera un posteo de Instagram, con el nombre como usuario arriba y corazones de '
                        .'adorno.',
                    'La reseña como una burbuja de chat, con el nombre y las estrellas en el encabezado.',
                ],
            ],
            [
                'key' => 'educational',
                'name' => 'Educativo',
                'description' => 'Lo que tus clientes necesitan saber',
                'instructions' => 'Cada idea enseña una sola cosa concreta y útil para elegir, comprar o usar bien lo '
                    .'que vende el negocio. Sale de lo que preguntan en los chats o de lo que el dueño cuenta en su '
                    .'audio; si existe, usá la respuesta del negocio (owner_answer). Podés completar con conocimiento '
                    .'general del rubro, pero nunca inventes datos propios del negocio: precios, medidas, stock o '
                    .'plazos. El título promete lo que se aprende, por ejemplo "Qué sustrato elegir según la etapa del '
                    .'cultivo". La pieza es un carrusel: una placa de gancho, de 2 a 4 placas con una idea cada una y '
                    .'una placa de cierre que invite a consultar. Si alcanza con un solo consejo, una placa.',
                'inputs' => [
                    'whatsapp_questions',
                    'audio_insights',
                    'brand_faq',
                    'audio_transcripts',
                    'uploaded_documents',
                    'website_pages',
                    'whatsapp_purposes',
                ],
                'angles' => [
                    'Explicá en pasos cortos cómo hacer algo.',
                    'Mostrá los errores más comunes y cómo evitarlos.',
                    'Compará dos opciones y decí cuándo conviene cada una.',
                    'Tomá una creencia equivocada del rubro y desarmala.',
                ],
                'layouts' => [
                    'El texto en grande, con un número bien visible al lado cuando la placa es parte de una secuencia.',
                    'Como una tarjeta: un ícono simple del tema arriba y el texto abajo.',
                    'El texto en una mitad de la placa y una imagen del tema en la otra.',
                    'Una frase corta y grande arriba y la explicación más chica debajo.',
                ],
            ],
        ];

        $contentTypeService = resolve(ContentTypeService::class);
        foreach ($contentTypes as $contentTypeAttributes) {
            $activeContentTypeExists = $contentTypeService->findOneByKey($contentTypeAttributes['key']) !== null;
            if (!$activeContentTypeExists) {
                $contentTypeService->create($contentTypeAttributes);
            }
        }
    }

}

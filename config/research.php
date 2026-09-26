<?php

return [

    'website' => [
        'analysis_model' => 'gpt-6-luna',
    ],

    'instagram' => [
        'posts_limit' => 6,
        'analysis_model' => 'gpt-6-luna',
    ],

    'meta_ads' => [
        'ads_limit' => 6,
        'analysis_model' => 'gpt-6-luna',
    ],

    'google_reviews' => [
        'reviews_limit' => 1000,
        'analysis_model' => 'gpt-6-luna',
    ],

    'whatsapp_conversations' => [
        'conversations_limit' => 500,
        'analysis_model' => 'gpt-6-luna',
    ],

    'audio' => [
        'analysis_model' => 'gpt-6-luna',
        'transcription_model' => 'gpt-transcribe',
    ],

    'uploaded_files' => [
        'analysis_model' => 'gpt-6-luna',
    ],

    // Las investigaciones de los competidores tienen su propia configuración, aunque hoy repita la de la marca.
    'competitors' => [

        'website' => [
            'analysis_model' => 'gpt-6-luna',
        ],

        'instagram' => [
            'posts_limit' => 6,
            'analysis_model' => 'gpt-6-luna',
        ],

        'meta_ads' => [
            'ads_limit' => 6,
            'analysis_model' => 'gpt-6-luna',
        ],

        'google_reviews' => [
            'reviews_limit' => 1000,
            'analysis_model' => 'gpt-6-luna',
        ],

        // El cruce de la marca con sus competidores.
        'brand_competition' => [
            'analysis_model' => 'gpt-6-luna',
        ],

    ],

];

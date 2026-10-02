<?php

use App\Services\ContentTypeInputs\GoogleReviewsInput;
use App\Services\ContentTypeInputs\GoogleReviewScoreInput;
use App\Services\ContentTypeInputs\GoogleReviewStaffInput;
use App\Services\ContentTypeInputs\GoogleReviewProductsInput;
use App\Services\ContentTypeInputs\GoogleReviewStrengthsInput;

return [

    'ideas' => [
        'model' => 'gpt-6-luna',
    ],

    'pieces' => [
        'model' => 'gpt-6-luna',
    ],

    // Las entradas del cerebro que nombran los tipos de contenido en inputs, con la clase que lee cada una. Un nombre
    // que todavía no está acá no tiene clase y no cuenta.
    'inputs' => [
        'google_reviews' => GoogleReviewsInput::class,
        'google_review_strengths' => GoogleReviewStrengthsInput::class,
        'google_review_products' => GoogleReviewProductsInput::class,
        'google_review_staff' => GoogleReviewStaffInput::class,
        'google_review_score' => GoogleReviewScoreInput::class,
    ],

];

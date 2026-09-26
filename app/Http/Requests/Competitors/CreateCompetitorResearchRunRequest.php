<?php

namespace App\Http\Requests\Competitors;

use App\Http\Requests\AuthenticatedRequest;


class CreateCompetitorResearchRunRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:website,instagram,meta_ads,google_reviews'],
        ];
    }


    public function messages(): array
    {
        return [
            'type.required' => 'Indica qué fuente quieres analizar.',
            'type.in' => 'Esa fuente no se puede analizar.',
        ];
    }

}

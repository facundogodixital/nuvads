<?php

namespace App\Http\Requests\KnowledgeInsights;

use App\Http\Requests\AuthenticatedRequest;


class ListKnowledgeInsightsRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        return [
            'types' => ['required', 'array', 'list'],
            'types.*' => ['string', 'distinct', 'in:website_brand_analysis,website_insight'],
        ];
    }


    public function messages(): array
    {
        return [
            'types.required' => 'Indica qué tipos de conclusiones quieres ver.',
            'types.*.in' => 'El tipo de conclusión no existe.',
        ];
    }

}

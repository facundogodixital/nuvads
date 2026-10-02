<?php

namespace App\Http\Requests\Ideas;

use App\Http\Requests\AuthenticatedRequest;


class GenerateSuggestedPieceRequest extends AuthenticatedRequest
{


    // Las indicaciones que escribe el usuario para esta escritura, opcionales.
    public function rules(): array
    {
        return [
            'instructions' => ['nullable', 'string', 'max:500'],
        ];
    }


    public function messages(): array
    {
        return [
            'instructions.max' => 'Las indicaciones pueden tener hasta 500 caracteres.',
        ];
    }

}

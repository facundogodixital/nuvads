<?php

namespace App\Http\Requests\Ideas;

use App\Http\Requests\AuthenticatedRequest;


class ListIdeasRequest extends AuthenticatedRequest
{


    // El estado de las ideas que se listan. Es un texto abierto: no se valida contra una lista de estados.
    public function rules(): array
    {
        return [
            'status' => ['required', 'string'],
        ];
    }

}

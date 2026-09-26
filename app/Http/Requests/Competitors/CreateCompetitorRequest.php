<?php

namespace App\Http\Requests\Competitors;

// Dar de alta acepta los mismos campos que editar y normaliza los enlaces igual; solo el nombre pasa a ser obligatorio.
class CreateCompetitorRequest extends UpdateCompetitorRequest
{


    public function rules(): array
    {
        return [...parent::rules(), 'name' => ['required', 'string', 'max:255']];
    }

}

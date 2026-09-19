<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;


class ExchangeLoginCodeRequest extends FormRequest
{


    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'verifier' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }

}

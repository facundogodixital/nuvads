<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;


class GoogleRedirectRequest extends FormRequest
{


    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ];
    }

}

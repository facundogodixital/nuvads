<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;


class GoogleCallbackRequest extends FormRequest
{


    public function rules(): array
    {
        return [
            'state' => ['required', 'string'],
            'error' => ['nullable', 'string'],
            'code' => ['required_without:error', 'nullable', 'string'],
        ];
    }

}

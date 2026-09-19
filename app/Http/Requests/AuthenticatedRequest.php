<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;


class AuthenticatedRequest extends FormRequest
{

    public User $user;
    public Client $client;


    protected function prepareForValidation(): void
    {
        // Solo se copian los atributos internos preparados por los middlewares, nunca datos de entrada.
        $this->user = $this->attributes->get('authenticated_user');
        $this->client = $this->attributes->get('authenticated_client');
    }


    public function rules(): array
    {
        return [];
    }

}

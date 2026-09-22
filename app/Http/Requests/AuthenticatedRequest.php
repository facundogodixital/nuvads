<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Brand;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;


class AuthenticatedRequest extends FormRequest
{

    public User $user;
    public Brand $brand;
    public Client $client;


    public function rules(): array
    {
        return [];
    }


    protected function prepareForValidation(): void
    {
        // Solo se copian los atributos internos preparados por los middlewares, nunca datos de entrada.
        $this->user = $this->attributes->get('user');
        $this->brand = $this->attributes->get('brand');
        $this->client = $this->attributes->get('client');
    }

}

<?php

namespace App\Http\Requests\Brands;

use App\Http\Requests\AuthenticatedRequest;


class UpdateBrandRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        return [
            'google_maps_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'website_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'instagram_username' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9._]+$/'],
        ];
    }


    public function messages(): array
    {
        return [
            'google_maps_url.url' => 'Ingresa un enlace válido que comience con https:// o http://.',
            'website_url.url' => 'Ingresa un enlace válido que comience con https:// o http://.',
            'instagram_username.regex' => 'Ingresa un usuario de Instagram o el enlace de su perfil.',
        ];
    }


    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $instagramUsername = $this->input('instagram_username');
        if (!is_string($instagramUsername)) {
            return;
        }

        $instagramUsername = trim($instagramUsername);
        $profilePattern = '~^(?:https?://)?(?:www\.)?instagram\.com/([a-z0-9._]+)/?(?:[?#].*)?$~i';
        $isProfileUrl = preg_match($profilePattern, $instagramUsername, $matches) === 1;
        if ($isProfileUrl) {
            $instagramUsername = $matches[1];
        } else {
            $instagramUsername = preg_replace('/^@/', '', $instagramUsername);
        }

        $this->merge(['instagram_username' => strtolower($instagramUsername)]);
    }

}

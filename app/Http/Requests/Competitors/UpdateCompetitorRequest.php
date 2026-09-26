<?php

namespace App\Http\Requests\Competitors;

use App\Http\Requests\AuthenticatedRequest;


class UpdateCompetitorRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        // Las columnas TEXT admiten 65535 bytes: 16000 caracteres cubren también texto Unicode.
        $textRules = ['sometimes', 'nullable', 'string', 'max:16000'];

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'google_maps_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'website_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'instagram_username' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9._]+$/'],
            'meta_ads_url' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
                'regex:~^https://www\.facebook\.com/[A-Za-z0-9.]+$~',
                // profile.php?id= identifica las páginas sin nombre, que por ahora no se aceptan.
                'not_regex:~\.php$~i',
            ],
            'competitor_offer_description' => $textRules,
            'competitor_customers_description' => $textRules,
            'competitor_strengths_description' => $textRules,
            'competitor_weaknesses_description' => $textRules,
            'competitor_communication_description' => $textRules,
            'competitor_differentiators_description' => $textRules,
        ];
    }


    public function messages(): array
    {
        $urlMessage = 'Ingresa un enlace válido que comience con https:// o http://.';
        $metaAdsUrlMessage = 'Ingresa el enlace de su página de Facebook, como https://www.facebook.com/sumarca.';

        return [
            'name.required' => 'Ingresa el nombre del competidor.',
            'website_url.url' => $urlMessage,
            'google_maps_url.url' => $urlMessage,
            'instagram_username.regex' => 'Ingresa un usuario de Instagram o el enlace de su perfil.',
            'meta_ads_url.regex' => $metaAdsUrlMessage,
            'meta_ads_url.not_regex' => $metaAdsUrlMessage,
        ];
    }


    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $instagramUsername = $this->input('instagram_username');
        if (is_string($instagramUsername)) {
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

        // La página de Facebook se guarda como https://www.facebook.com/<nombre>. Lo que no tenga esa forma queda
        // como llegó y lo rechazan las reglas.
        $metaAdsUrl = $this->input('meta_ads_url');
        if (is_string($metaAdsUrl)) {
            $metaAdsUrl = trim($metaAdsUrl);
            $pagePattern = '~^(?:https?://)?(?:www\.|m\.)?facebook\.com/([a-z0-9.]+)/?(?:[?#].*)?$~i';
            $isPageUrl = preg_match($pagePattern, $metaAdsUrl, $matches) === 1;
            if ($isPageUrl) {
                $metaAdsUrl = "https://www.facebook.com/{$matches[1]}";
            }

            $this->merge(['meta_ads_url' => $metaAdsUrl]);
        }
    }

}

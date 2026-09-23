<?php

namespace App\Http\Requests\Brands;

use App\Http\Requests\AuthenticatedRequest;


class UpdateBrandRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        // Las columnas TEXT admiten 65535 bytes: 16000 caracteres cubren también texto Unicode.
        $colorKeys = 'primary,secondary,accent,background,text';
        $text = ['sometimes', 'nullable', 'string', 'max:16000'];
        $hexColor = ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'];

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'google_maps_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'website_url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'instagram_username' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9._]+$/'],
            'brand_logos' => ['sometimes', 'nullable', 'array', 'list'],
            'brand_logos.*' => ['string', 'url:http,https', 'max:2048'],
            'brand_colors' => ['sometimes', 'nullable', "array:{$colorKeys}", "required_array_keys:{$colorKeys}"],
            'brand_colors.primary' => $hexColor,
            'brand_colors.secondary' => $hexColor,
            'brand_colors.accent' => $hexColor,
            'brand_colors.background' => $hexColor,
            'brand_colors.text' => $hexColor,
            'brand_fonts' => ['sometimes', 'nullable', 'array:heading,body', 'required_array_keys:heading,body'],
            'brand_fonts.heading' => ['nullable', 'string', 'max:255'],
            'brand_fonts.body' => ['nullable', 'string', 'max:255'],
            'brand_offer_description' => $text,
            'brand_differentiators_description' => $text,
            'brand_history_description' => $text,
            'brand_customers_description' => $text,
            'brand_customers_needs_description' => $text,
            'brand_visual_style_description' => $text,
            'brand_tone_of_voice_description' => $text,
            'brand_customers_valued_aspects_description' => $text,
            'brand_customers_faq_description' => $text,
            'brand_communication_topics_description' => $text,
            'brand_content_opportunities_description' => $text,
        ];
    }


    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre de tu marca.',
            'brand_colors.*.regex' => 'Elige un color válido.',
            'google_maps_url.url' => 'Ingresa un enlace válido que comience con https:// o http://.',
            'website_url.url' => 'Ingresa un enlace válido que comience con https:// o http://.',
            'instagram_username.regex' => 'Ingresa un usuario de Instagram o el enlace de su perfil.',
            'brand_logos.*.url' => 'Ingresa un enlace de imagen válido que comience con https:// o http://.',
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

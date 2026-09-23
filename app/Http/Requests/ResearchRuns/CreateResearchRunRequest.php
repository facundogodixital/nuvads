<?php

namespace App\Http\Requests\ResearchRuns;

use App\Services\ResearchRunService;
use Illuminate\Validation\Validator;
use App\Http\Requests\AuthenticatedRequest;


class CreateResearchRunRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:website,instagram'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }


    public function messages(): array
    {
        return [
            'type.required' => 'Indica el tipo de investigación.',
            'type.in' => 'El tipo de investigación no es válido.',
        ];
    }


    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('type');
            $isWebsiteMissing = $type === 'website' && $this->brand->website_url === null;
            if ($isWebsiteMissing) {
                $validator->errors()->add('website_url', 'Guarda el sitio web de tu marca antes de analizarlo.');
                return;
            }
            $isInstagramMissing = $type === 'instagram' && $this->brand->instagram_username === null;
            if ($isInstagramMissing) {
                $validator->errors()->add(
                    'instagram_username', 'Guarda el usuario de Instagram de tu marca antes de analizarlo.',
                );
                return;
            }

            $activeResearchRun = resolve(ResearchRunService::class)->findOneActiveForBrand($this->brand, $type);
            if ($activeResearchRun !== null) {
                $activeResearchMessages = [
                    'website' => 'Ya hay un análisis del sitio web en curso.',
                    'instagram' => 'Ya hay un análisis de Instagram en curso.',
                ];
                $validator->errors()->add('type', $activeResearchMessages[$type]);
                return;
            }
        }];
    }

}

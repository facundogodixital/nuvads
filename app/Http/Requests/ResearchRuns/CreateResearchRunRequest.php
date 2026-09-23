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
            'type' => ['required', 'string', 'in:website'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }


    public function messages(): array
    {
        return [
            'type.required' => 'Indica el tipo de investigación.',
            'type.in' => 'Por ahora solo está disponible la investigación del sitio web.',
        ];
    }


    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->brand->website_url === null) {
                $validator->errors()->add('website_url', 'Guarda el sitio web de tu marca antes de analizarlo.');
                return;
            }

            $type = $this->input('type');
            $activeResearchRun = resolve(ResearchRunService::class)->findOneActiveForBrand($this->brand, $type);
            if ($activeResearchRun !== null) {
                $validator->errors()->add('type', 'Ya hay un análisis del sitio web en curso.');
                return;
            }
        }];
    }

}

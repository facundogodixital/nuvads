<?php

namespace App\Http\Requests\UploadedFiles;

use App\Services\ResearchRunService;
use Illuminate\Validation\Validator;
use App\Http\Requests\AuthenticatedRequest;


class DeleteUploadedFileRequest extends AuthenticatedRequest
{


    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Borrar un archivo rehace el análisis de los que quedan: no puede pisarse con otro en curso.
            $activeResearchRun = resolve(ResearchRunService::class)->findOneActiveForBrand(
                $this->brand, 'uploaded_files',
            );
            if ($activeResearchRun !== null) {
                $validator->errors()->add(
                    'uploaded_file', 'Espera a que termine el análisis de tus archivos para borrar uno.',
                );
                return;
            }
        }];
    }

}

<?php

namespace App\Http\Requests\ResearchRuns;

use App\Services\ResearchRunService;
use Illuminate\Validation\Validator;
use App\Services\UploadedFileService;
use App\Http\Requests\AuthenticatedRequest;


class CreateResearchRunRequest extends AuthenticatedRequest
{


    public function rules(): array
    {
        $uploadedFileExtensions = [
            ...UploadedFileService::IMAGE_EXTENSIONS,
            ...UploadedFileService::DOCUMENT_EXTENSIONS,
        ];

        return [
            'type' => [
                'required',
                'string',
                'in:website,instagram,meta_ads,google_reviews,whatsapp_conversations,audio,uploaded_files',
            ],
            'zip_file' => ['required_if:type,whatsapp_conversations', 'file', 'mimes:zip'],
            // Lo que graba el navegador: webm en Chrome y Firefox, mp4 en Safari.
            'audio_file' => ['required_if:type,audio', 'file', 'mimes:webm,mp4,m4a'],
            // Los archivos solo se aceptan en una subida de fotos y documentos; en los otros tipos se descartan.
            'files' => ['exclude_unless:type,uploaded_files', 'required_if:type,uploaded_files', 'array', 'list'],
            // La extensión decide si es foto o documento; lo que OpenAI no pueda leer queda marcado al analizarlo.
            'files.*' => [
                'exclude_unless:type,uploaded_files', 'file', 'extensions:'.implode(',', $uploadedFileExtensions),
            ],
        ];
    }


    public function messages(): array
    {
        return [
            'type.required' => 'Indica el tipo de investigación.',
            'type.in' => 'El tipo de investigación no es válido.',
            'zip_file.required_if' => 'Sube el archivo .zip con tus conversaciones de WhatsApp.',
            'zip_file.uploaded' => 'No se pudo subir el archivo. Revisa que no pese más de 20 MB.',
            'zip_file.file' => 'No se pudo subir el archivo.',
            'zip_file.mimes' => 'El archivo tiene que ser un .zip.',
            'audio_file.required_if' => 'Graba un audio para analizarlo.',
            'audio_file.uploaded' => 'No se pudo subir el audio. Revisa que no pese más de 20 MB.',
            'audio_file.file' => 'No se pudo subir el audio.',
            'audio_file.mimes' => 'No podemos leer el formato de este audio.',
            'files.required_if' => 'Elige las fotos o los documentos que quieres subir.',
            'files.array' => 'No se pudieron subir los archivos.',
            'files.list' => 'No se pudieron subir los archivos.',
            'files.*.uploaded' => 'No se pudo subir un archivo. Revisa que entre todos no pesen más de 20 MB.',
            'files.*.file' => 'No se pudo subir un archivo.',
            'files.*.extensions' => 'Solo se pueden subir fotos (jpg, png, webp o gif) y documentos (pdf, Word, Excel, '
                .'PowerPoint o texto).',
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
            $isMetaAdsUrlMissing = $type === 'meta_ads' && $this->brand->meta_ads_url === null;
            if ($isMetaAdsUrlMissing) {
                $validator->errors()->add(
                    'meta_ads_url', 'Guarda el enlace de tu página de Facebook antes de analizarla.',
                );
                return;
            }
            $isGoogleMapsUrlMissing = $type === 'google_reviews' && $this->brand->google_maps_url === null;
            if ($isGoogleMapsUrlMissing) {
                $validator->errors()->add(
                    'google_maps_url', 'Guarda el enlace de tu negocio en Google Maps antes de analizarlo.',
                );
                return;
            }

            $activeResearchRun = resolve(ResearchRunService::class)->findOneActiveForBrand($this->brand, $type);
            if ($activeResearchRun !== null) {
                $activeResearchMessages = [
                    'website' => 'Ya hay un análisis del sitio web en curso.',
                    'instagram' => 'Ya hay un análisis de Instagram en curso.',
                    'meta_ads' => 'Ya hay un análisis de tus anuncios en curso.',
                    'google_reviews' => 'Ya hay un análisis de tus reseñas de Google en curso.',
                    'whatsapp_conversations' => 'Ya hay un análisis de tus conversaciones de WhatsApp en curso.',
                    'audio' => 'Ya hay un análisis de tu audio en curso.',
                    'uploaded_files' => 'Espera a que termine el análisis de tus archivos para subir más.',
                ];
                $validator->errors()->add('type', $activeResearchMessages[$type]);
                return;
            }
        }];
    }

}

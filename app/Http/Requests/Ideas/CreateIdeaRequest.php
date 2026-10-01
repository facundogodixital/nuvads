<?php

namespace App\Http\Requests\Ideas;

use App\Services\ContentTypeService;
use Illuminate\Validation\Validator;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;
use App\Http\Requests\AuthenticatedRequest;


class CreateIdeaRequest extends AuthenticatedRequest
{


    // Lo que trae la idea sugerida que eligió el usuario.
    public function rules(): array
    {
        return [
            'content_type_id' => ['required', 'integer:strict'],
            'title' => ['required', 'string', 'max:255'],
            'angle' => ['present', 'nullable', 'string', 'max:255'],
            'knowledge_insight_ids' => ['present', 'array', 'list'],
            'knowledge_insight_ids.*' => ['integer:strict'],
            'knowledge_source_ids' => ['present', 'array', 'list'],
            'knowledge_source_ids.*' => ['integer:strict'],
        ];
    }


    // El tipo tiene que estar activo; el ángulo, ser uno de los del tipo o ninguno; y las conclusiones y las fuentes,
    // ser de la marca.
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $contentType = resolve(ContentTypeService::class)->find($this->input('content_type_id'));
                if ($contentType === null) {
                    $validator->errors()->add('content_type_id', 'No encontramos ese tipo de contenido.');
                    return;
                }

                $angle = $this->input('angle');
                $isAngleOfContentType = in_array($angle, $contentType->angles ?? [], true);
                $isAngleAllowed = $angle === null || $isAngleOfContentType;
                if (!$isAngleAllowed) {
                    $validator->errors()->add('angle', 'Esta idea no es de este tipo de contenido.');
                    return;
                }

                $knowledgeInsightService = resolve(KnowledgeInsightService::class);
                $foreignReferencesMessage = 'Esta idea usa material que ya no está en tu marca. Busca otras ideas.';
                $hasForeignKnowledgeInsights = collect($this->input('knowledge_insight_ids'))->contains(
                    function (int $knowledgeInsightId) use ($knowledgeInsightService): bool {
                        return $knowledgeInsightService->find($this->brand, $knowledgeInsightId) === null;
                    },
                );
                if ($hasForeignKnowledgeInsights) {
                    $validator->errors()->add('knowledge_insight_ids', $foreignReferencesMessage);
                    return;
                }

                $knowledgeSourceIds = $this->input('knowledge_source_ids');
                $knowledgeSourceService = resolve(KnowledgeSourceService::class);
                $knowledgeSources = $knowledgeSourceService->findByIds($this->brand, $knowledgeSourceIds);
                $hasForeignKnowledgeSources = array_diff($knowledgeSourceIds, $knowledgeSources->modelKeys()) !== [];
                if ($hasForeignKnowledgeSources) {
                    $validator->errors()->add('knowledge_source_ids', $foreignReferencesMessage);
                    return;
                }
            },
        ];
    }

}

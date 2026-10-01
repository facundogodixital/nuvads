<?php

namespace App\Http\Controllers\API;

use App\Helpers\SystemHelper;
use App\Services\IdeaService;
use Illuminate\Http\JsonResponse;
use App\Services\IdeaGenerationService;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Requests\Ideas\CreateIdeaRequest;


class IdeaController extends ApiController
{


    // El pedido espera la respuesta del modelo: el límite de tiempo de PHP sube a 120 segundos.
    public function generateSuggestedIdeas(AuthenticatedRequest $request, int $contentTypeId): JsonResponse
    {
        resolve(SystemHelper::class)->setTimeLimit(120);

        $suggestedIdeas = resolve(IdeaGenerationService::class)->generateSuggestedIdeas(
            $request->brand, $contentTypeId,
        );
        return $this->respond($suggestedIdeas);
    }


    public function create(CreateIdeaRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $idea = resolve(IdeaService::class)->create($request->brand, $attributes);
        return $this->respond($idea, 201);
    }

}

<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\Brand;
use App\Repositories\IdeaRepository;


class IdeaService
{

    private IdeaRepository $ideaRepository;


    public function __construct(IdeaRepository $ideaRepository)
    {
        $this->ideaRepository = $ideaRepository;
    }


    // Guarda la idea que eligió el usuario, en chosen y con el modelo que propone las ideas.
    public function create(Brand $brand, array $attributes): Idea
    {
        return $this->ideaRepository->create($brand, [
            ...$attributes,
            'status' => 'chosen',
            'model' => config('content.ideas.model'), // gpt-6-luna
        ]);
    }


    // Los IDs de las fuentes que ya muestra o usa alguna idea de la marca, sin repetir.
    public function getUsedKnowledgeSourceIds(Brand $brand): array
    {
        return $this->ideaRepository->list($brand)
            ->flatMap(fn (Idea $idea): array => $idea->knowledge_source_ids)
            ->unique()
            ->values()
            ->all();
    }

}

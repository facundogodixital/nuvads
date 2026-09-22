<?php

namespace App\Services\Dispatchers;

use App\Jobs\Research\Website\ResearchWebsiteJob;


class ResearchDispatcherService
{


    public function dispatchResearchWebsiteJob(int $researchRunId): void
    {
        ResearchWebsiteJob::dispatch($researchRunId)->onQueue('research_queue');
    }

}

<?php

namespace App\Services\Dispatchers;

use App\Jobs\Research\Website\CheckWebsiteScrapingJob;
use App\Jobs\Research\Website\StartWebsiteScrapingJob;
use App\Jobs\Research\Website\AnalyzeWebsiteContentJob;


class ResearchDispatcherService
{


    public function dispatchStartWebsiteScrapingJob(int $researchRunId): void
    {
        StartWebsiteScrapingJob::dispatch($researchRunId)->onQueue('scraping_queue')->beforeCommit();
    }


    public function dispatchCheckWebsiteScrapingJob(int $researchRunId): void
    {
        CheckWebsiteScrapingJob::dispatch($researchRunId)
            ->onQueue('scraping_queue')
            ->delay(now()->addSeconds(15))
            ->beforeCommit();
    }


    public function dispatchAnalyzeWebsiteContentJob(int $researchRunId): void
    {
        AnalyzeWebsiteContentJob::dispatch($researchRunId)->onQueue('analysis_queue')->beforeCommit();
    }


    public function redispatchAfterOverlap(string $jobClass, int $researchRunId): void
    {
        $queue = match ($jobClass) {
            StartWebsiteScrapingJob::class, CheckWebsiteScrapingJob::class => 'scraping_queue',
            AnalyzeWebsiteContentJob::class => 'analysis_queue',
        };

        // Una entrega nueva no consume los intentos reservados para fallos de la operación.
        $jobClass::dispatch($researchRunId)->onQueue($queue)->delay(now()->addSeconds(15))->beforeCommit();
    }

}

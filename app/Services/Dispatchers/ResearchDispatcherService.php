<?php

namespace App\Services\Dispatchers;

use App\Jobs\Research\MetaAds\ResearchMetaAdsJob;
use App\Jobs\Research\Website\ResearchWebsiteJob;
use App\Jobs\Research\Instagram\ResearchInstagramJob;
use App\Jobs\Research\GoogleReviews\ResearchGoogleReviewsJob;
use App\Jobs\Research\WhatsAppConversations\ResearchWhatsAppConversationsJob;


class ResearchDispatcherService
{


    public function dispatchResearchWebsiteJob(int $researchRunId): void
    {
        ResearchWebsiteJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchInstagramJob(int $researchRunId): void
    {
        ResearchInstagramJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchMetaAdsJob(int $researchRunId): void
    {
        ResearchMetaAdsJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchGoogleReviewsJob(int $researchRunId): void
    {
        ResearchGoogleReviewsJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchWhatsAppConversationsJob(int $researchRunId): void
    {
        ResearchWhatsAppConversationsJob::dispatch($researchRunId)->onQueue('research_queue');
    }

}

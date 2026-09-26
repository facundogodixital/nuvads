<?php

namespace App\Services\Dispatchers;

use App\Jobs\Research\Audio\ResearchAudioJob;
use App\Jobs\Research\MetaAds\ResearchMetaAdsJob;
use App\Jobs\Research\Website\ResearchWebsiteJob;
use App\Jobs\Research\Instagram\ResearchInstagramJob;
use App\Jobs\Research\GoogleReviews\ResearchGoogleReviewsJob;
use App\Jobs\Research\UploadedFiles\ResearchUploadedFilesJob;
use App\Jobs\Research\Competitors\ResearchBrandCompetitionJob;
use App\Jobs\Research\Competitors\ResearchCompetitorMetaAdsJob;
use App\Jobs\Research\Competitors\ResearchCompetitorWebsiteJob;
use App\Jobs\Research\Competitors\ResearchCompetitorInstagramJob;
use App\Jobs\Research\Competitors\ResearchCompetitorGoogleReviewsJob;
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


    public function dispatchResearchAudioJob(int $researchRunId): void
    {
        ResearchAudioJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchUploadedFilesJob(int $researchRunId): void
    {
        ResearchUploadedFilesJob::dispatch($researchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchCompetitorWebsiteJob(int $competitorResearchRunId): void
    {
        ResearchCompetitorWebsiteJob::dispatch($competitorResearchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchCompetitorInstagramJob(int $competitorResearchRunId): void
    {
        ResearchCompetitorInstagramJob::dispatch($competitorResearchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchCompetitorMetaAdsJob(int $competitorResearchRunId): void
    {
        ResearchCompetitorMetaAdsJob::dispatch($competitorResearchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchCompetitorGoogleReviewsJob(int $competitorResearchRunId): void
    {
        ResearchCompetitorGoogleReviewsJob::dispatch($competitorResearchRunId)->onQueue('research_queue');
    }


    public function dispatchResearchBrandCompetitionJob(int $brandId): void
    {
        ResearchBrandCompetitionJob::dispatch($brandId)->onQueue('research_queue');
    }

}

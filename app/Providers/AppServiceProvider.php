<?php

namespace App\Providers;

use App\Helpers\S3Helper;
use App\Helpers\ApifyHelper;
use App\Helpers\OpenAIHelper;
use App\Helpers\SystemHelper;
use App\Services\IdeaService;
use App\Services\UserService;
use App\Services\BrandService;
use App\Helpers\DeepSeekHelper;
use App\Services\ClientService;
use App\Helpers\FirecrawlHelper;
use App\Helpers\GoogleOAuthHelper;
use App\Services\LoginCodeService;
use App\Services\CompetitorService;
use App\Services\GoogleAuthService;
use App\Helpers\IpGeolocationHelper;
use App\Repositories\IdeaRepository;
use App\Repositories\UserRepository;
use App\Services\ContentTypeService;
use App\Services\ResearchRunService;
use App\Repositories\BrandRepository;
use App\Repositories\ClientRepository;
use App\Services\AdministratorService;
use App\Services\AudioResearchService;
use App\Services\IdeaGenerationService;
use Illuminate\Support\ServiceProvider;
use App\Services\KnowledgeSourceService;
use App\Services\MetaAdsResearchService;
use App\Services\WebsiteResearchService;
use App\Services\CompetitorSourceService;
use App\Services\KnowledgeInsightService;
use App\Repositories\CompetitorRepository;
use App\Services\CompetitorInsightService;
use App\Services\InstagramResearchService;
use App\Repositories\ContentTypeRepository;
use App\Repositories\ResearchRunRepository;
use App\Repositories\AdministratorRepository;
use App\Services\CompetitorResearchRunService;
use App\Services\UploadedFilesResearchService;
use App\Helpers\WhatsAppConversationsZipHelper;
use App\Repositories\KnowledgeSourceRepository;
use App\Repositories\CompetitorSourceRepository;
use App\Repositories\KnowledgeInsightRepository;
use App\Repositories\CompetitorInsightRepository;
use App\Services\BrandCompetitionResearchService;
use App\Services\CompetitorMetaAdsResearchService;
use App\Services\CompetitorWebsiteResearchService;
use App\Services\CompetitorInstagramResearchService;
use App\Repositories\CompetitorResearchRunRepository;
use App\Services\ContentTypeInputs\GoogleReviewsInput;
use App\Services\WhatsAppConversationsResearchService;
use App\Services\Dispatchers\ResearchDispatcherService;
use App\Services\CompetitorGoogleReviewsResearchService;
use App\Services\ContentTypeInputs\GoogleReviewScoreInput;
use App\Services\ContentTypeInputs\GoogleReviewStaffInput;
use App\Services\ContentTypeInputs\GoogleReviewProductsInput;
use App\Services\ContentTypeInputs\GoogleReviewStrengthsInput;


class AppServiceProvider extends ServiceProvider
{


    /**
     * Registra los servicios y repositorios de la aplicación.
     */
    public function register(): void
    {
        $this->app->scoped(S3Helper::class);
        $this->app->scoped(ApifyHelper::class);
        $this->app->scoped(OpenAIHelper::class);
        $this->app->scoped(DeepSeekHelper::class);
        $this->app->scoped(FirecrawlHelper::class);
        $this->app->scoped(ResearchRunService::class);
        $this->app->scoped(WebsiteResearchService::class);
        $this->app->scoped(InstagramResearchService::class);
        $this->app->scoped(MetaAdsResearchService::class);
        $this->app->scoped(WhatsAppConversationsZipHelper::class);
        $this->app->scoped(WhatsAppConversationsResearchService::class);
        $this->app->scoped(AudioResearchService::class);
        $this->app->scoped(UploadedFilesResearchService::class);
        $this->app->scoped(ResearchRunRepository::class);
        $this->app->scoped(ResearchDispatcherService::class);
        $this->app->scoped(KnowledgeSourceService::class);
        $this->app->scoped(KnowledgeInsightService::class);
        $this->app->scoped(KnowledgeSourceRepository::class);
        $this->app->scoped(KnowledgeInsightRepository::class);
        $this->app->scoped(UserService::class);
        $this->app->scoped(BrandService::class);
        $this->app->scoped(LoginCodeService::class);
        $this->app->scoped(ClientService::class);
        $this->app->scoped(GoogleAuthService::class);
        $this->app->scoped(GoogleOAuthHelper::class);
        $this->app->scoped(IpGeolocationHelper::class);
        $this->app->scoped(UserRepository::class);
        $this->app->scoped(BrandRepository::class);
        $this->app->scoped(ClientRepository::class);
        $this->app->scoped(AdministratorService::class);
        $this->app->scoped(AdministratorRepository::class);
        $this->app->scoped(CompetitorService::class);
        $this->app->scoped(CompetitorRepository::class);
        $this->app->scoped(CompetitorSourceService::class);
        $this->app->scoped(CompetitorSourceRepository::class);
        $this->app->scoped(CompetitorInsightService::class);
        $this->app->scoped(CompetitorInsightRepository::class);
        $this->app->scoped(CompetitorResearchRunService::class);
        $this->app->scoped(CompetitorResearchRunRepository::class);
        $this->app->scoped(CompetitorWebsiteResearchService::class);
        $this->app->scoped(CompetitorInstagramResearchService::class);
        $this->app->scoped(CompetitorMetaAdsResearchService::class);
        $this->app->scoped(CompetitorGoogleReviewsResearchService::class);
        $this->app->scoped(BrandCompetitionResearchService::class);
        $this->app->scoped(ContentTypeService::class);
        $this->app->scoped(ContentTypeRepository::class);
        $this->app->scoped(IdeaService::class);
        $this->app->scoped(IdeaRepository::class);
        $this->app->scoped(IdeaGenerationService::class);
        $this->app->scoped(SystemHelper::class);
        $this->app->scoped(GoogleReviewsInput::class);
        $this->app->scoped(GoogleReviewStrengthsInput::class);
        $this->app->scoped(GoogleReviewProductsInput::class);
        $this->app->scoped(GoogleReviewStaffInput::class);
        $this->app->scoped(GoogleReviewScoreInput::class);
    }


    /**
     * Inicializa la aplicación.
     */
    public function boot(): void
    {
        //
    }

}

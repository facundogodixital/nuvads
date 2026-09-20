<?php

namespace App\Providers;

use App\Services\UserService;
use App\Services\BrandService;
use App\Services\ClientService;
use App\Helpers\GoogleOAuthHelper;
use App\Services\LoginCodeService;
use App\Services\GoogleAuthService;
use App\Helpers\IpGeolocationHelper;
use App\Repositories\UserRepository;
use App\Repositories\BrandRepository;
use App\Repositories\ClientRepository;
use App\Services\AdministratorService;
use Illuminate\Support\ServiceProvider;
use App\Repositories\AdministratorRepository;


class AppServiceProvider extends ServiceProvider
{


    /**
     * Registra los servicios y repositorios de la aplicación.
     */
    public function register(): void
    {
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
    }


    /**
     * Inicializa la aplicación.
     */
    public function boot(): void
    {
        //
    }

}

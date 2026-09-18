<?php

namespace App\Providers;

use App\Services\UserService;
use App\Services\ClientService;
use App\Helpers\GoogleOAuthHelper;
use App\Services\GoogleAuthService;
use App\Repositories\UserRepository;
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
        $this->app->scoped(ClientService::class);
        $this->app->scoped(GoogleAuthService::class);
        $this->app->scoped(GoogleOAuthHelper::class);
        $this->app->scoped(UserRepository::class);
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

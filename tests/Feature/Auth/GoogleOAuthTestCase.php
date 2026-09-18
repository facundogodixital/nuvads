<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;


abstract class GoogleOAuthTestCase extends TestCase
{

    protected MockHandler $googleResponses;


    protected function setUp(): void
    {
        parent::setUp();

        $this->googleResponses = new MockHandler();
        $httpClient = new Client(['handler' => HandlerStack::create($this->googleResponses)]);

        // Socialite conserva su validación de state; únicamente se reemplaza el transporte HTTP.
        Socialite::shouldReceive('buildProvider')->with(GoogleProvider::class, config('services.google'))
            ->andReturnUsing(function () use ($httpClient): GoogleProvider {
                $provider = new GoogleProvider(
                    $this->app['request'],
                    config('services.google.client_id'),
                    config('services.google.client_secret'),
                    config('services.google.redirect'),
                );

                return $provider->setHttpClient($httpClient);
            });
    }


    protected function queueGoogleProfile(array $profile): void
    {
        $this->googleResponses->append(
            new Response(200, [], json_encode(['access_token' => 'testing-token'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($profile, JSON_THROW_ON_ERROR)),
        );
    }

}

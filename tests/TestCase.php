<?php

namespace Tests;

use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;


abstract class TestCase extends LaravelTestCase
{


    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $configuration = $app['config'];
        $configuration->set('logging.default', 'null');
        $connection = $configuration->get('database.connections.mysql');

        $isTesting = $app->environment('testing');
        $hasTestingUser = $connection['username'] === 'nuvads_testing';
        $usesMysql = $configuration->get('database.default') === 'mysql';
        $hasTestingDatabase = $connection['database'] === 'nuvads_testing';
        $hasExplicitConnection = empty($connection['url']) && empty($connection['unix_socket']);

        if (!$isTesting || !$usesMysql || !$hasTestingDatabase || !$hasTestingUser || !$hasExplicitConnection) {
            throw new RuntimeException('Los tests requieren la base y el usuario exclusivos nuvads_testing.');
        }

        $hasTestingHost = $connection['host'] === 'mysql';
        $hasTestingPort = (string) $connection['port'] === '3306';
        if (!$hasTestingHost || !$hasTestingPort) {
            throw new RuntimeException('Los tests requieren el servicio mysql de Docker en su puerto interno 3306.');
        }

        // Se comprueba la conexión efectiva antes de que RefreshDatabase ejecute migrate:fresh.
        $usesDatabase = isset($this->traitsUsedByTest[RefreshDatabase::class]);
        if ($usesDatabase) {
            $database = $app['db']->connection()->selectOne('SELECT DATABASE() AS name, CURRENT_USER() AS account');
            $hasExpectedDatabase = $database->name === 'nuvads_testing';
            $hasExpectedAccount = $database->account === 'nuvads_testing@%';
            if (!$hasExpectedDatabase || !$hasExpectedAccount) {
                throw new RuntimeException('La conexión efectiva no corresponde a la base de tests.');
            }
        }

        return $app;
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Http::preventStrayRequests();
    }

}

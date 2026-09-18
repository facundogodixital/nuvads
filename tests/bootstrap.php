<?php

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$projectPath = dirname(__DIR__);
$hasTestingEnvironment = file_exists($projectPath.'/.env.testing');
if (!$hasTestingEnvironment) {
    throw new RuntimeException('Falta .env.testing. Ejecutá make test-setup antes de correr los tests.');
}

// Docker inyecta las credenciales de desarrollo: las de tests deben reemplazarlas también en getenv().
Dotenv::createUnsafeMutable($projectPath, '.env.testing')->load();

$configCachePath = getenv('APP_CONFIG_CACHE') ?: $projectPath.'/bootstrap/cache/config.php';
$hasCachedConfiguration = file_exists($configCachePath);
if ($hasCachedConfiguration) {
    throw new RuntimeException('Hay configuración cacheada. Ejecutá php artisan config:clear antes de los tests.');
}

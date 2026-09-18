<?php

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

umask(0077);
$projectPath = dirname(__DIR__);
$environmentPath = $projectPath.'/.env.testing';

$hasTestingEnvironment = file_exists($environmentPath);
if (!$hasTestingEnvironment) {
    $databasePassword = bin2hex(random_bytes(32));
    $appKey = 'base64:'.base64_encode(random_bytes(32));
    $environment = file_get_contents($projectPath.'/.env.testing.example');
    $environment = str_replace('APP_KEY=', "APP_KEY={$appKey}", $environment);
    $environment = str_replace('DB_PASSWORD=', "DB_PASSWORD={$databasePassword}", $environment);

    file_put_contents($environmentPath, $environment);
}

// El directorio tiene ACL heredadas; umask por sí solo no garantiza permisos privados.
chmod($environmentPath, 0600);

$environment = Dotenv::parse(file_get_contents($environmentPath));
$databasePassword = $environment['DB_PASSWORD'] ?? '';
$hasTestingUser = ($environment['DB_USERNAME'] ?? '') === 'nuvads_testing';
$hasTestingDatabase = ($environment['DB_DATABASE'] ?? '') === 'nuvads_testing';
$hasGeneratedPassword = preg_match('/\A[a-f0-9]{64}\z/', $databasePassword) === 1;

if (!$hasTestingDatabase || !$hasTestingUser || !$hasGeneratedPassword) {
    fwrite(STDERR, "La configuración de .env.testing no corresponde al entorno aprobado de tests.\n");
    exit(1);
}

// El SQL viaja por stdin a MySQL; la contraseña no aparece en argumentos ni en la consola.
echo "CREATE DATABASE IF NOT EXISTS nuvads_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
echo "CREATE USER IF NOT EXISTS 'nuvads_testing'@'%' IDENTIFIED BY '{$databasePassword}';\n";
echo "ALTER USER 'nuvads_testing'@'%' IDENTIFIED BY '{$databasePassword}';\n";
echo "REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'nuvads_testing'@'%';\n";
echo "GRANT ALL PRIVILEGES ON `nuvads\\_testing`.* TO 'nuvads_testing'@'%';\n";

<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

// O .env fica na raiz do projeto, fora de public/. Testes apontam para outro arquivo.
App\Core\Env::load(getenv('MOTOSTOCK_ENV_FILE') ?: BASE_PATH . '/.env');

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

if (App\Core\Env::bool('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // erros vão para a página de erro de debug, não soltos no HTML
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

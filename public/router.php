<?php
// Somente para desenvolvimento: php -S localhost:8080 -t public public/router.php
$file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (PHP_SAPI === 'cli-server' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}
require __DIR__ . '/index.php';

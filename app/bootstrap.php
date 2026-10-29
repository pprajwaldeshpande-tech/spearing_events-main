<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use EventSpot\Config;
use EventSpot\Http;

require dirname(__DIR__) . '/vendor/autoload.php';

if (is_file(dirname(__DIR__) . '/.env')) {
    Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
}

date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Kolkata'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php-error.log');

session_name(Config::get('SESSION_NAME', 'eventspot_session'));
session_set_cookie_params([
    'httponly' => true,
    'secure' => Config::bool('SESSION_SECURE'),
    'samesite' => 'Lax',
    'path' => '/',
]);
session_start();

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    Http::error('SERVER_ERROR', 'An unexpected server error occurred.', 500);
});
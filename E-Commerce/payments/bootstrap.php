<?php

require_once __DIR__ . '/../db.php';

function payments_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_readable($autoload)) {
        throw new RuntimeException('Composer vendor missing. Run: cd E-Commerce && php composer.phar install');
    }
    require_once $autoload;
    $loaded = true;
}

function site_absolute_url(string $path, array $params = []): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = function_exists('site_base_path') ? site_base_path() : '';
    $url = $scheme . '://' . $host . ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

function iyzico_usd_to_try_rate(): float
{
    $rate = getenv('IYZICO_USD_TRY_RATE');
    if ($rate !== false && is_numeric($rate) && (float) $rate > 0) {
        return (float) $rate;
    }
    return 34.0;
}

function format_try_amount(float $amount): string
{
    return number_format(round($amount, 2), 2, '.', '');
}

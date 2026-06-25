<?php

require_once __DIR__ . '/../db.php';

function iyzico_sdk_options_path(): string
{
    return __DIR__ . '/../vendor/iyzico/iyzipay-php/src/Iyzipay/Options.php';
}

function iyzico_vendor_available(): bool
{
    $base = __DIR__ . '/../vendor';
    return is_readable($base . '/autoload.php')
        && is_readable($base . '/composer/autoload_real.php')
        && is_readable(iyzico_sdk_options_path());
}

function payments_autoload(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $vendorRoot = __DIR__ . '/../vendor';
    $autoload = $vendorRoot . '/autoload.php';
    $autoloadReal = $vendorRoot . '/composer/autoload_real.php';
    $sdkOptions = iyzico_sdk_options_path();

    if (!is_readable($autoload) || !is_readable($autoloadReal)) {
        throw new RuntimeException(
            'Composer vendor incomplete. Upload vendor.zip to htdocs and extract so vendor/autoload.php exists.'
        );
    }
    if (!is_readable($sdkOptions)) {
        throw new RuntimeException(
            'iyzico SDK missing on server. Re-upload vendor.zip to htdocs and extract fully — required file: vendor/iyzico/iyzipay-php/src/Iyzipay/Options.php'
        );
    }

    require_once $autoload;

    if (!class_exists('Iyzipay\\Options', false)) {
        $bootstrap = $vendorRoot . '/iyzico/iyzipay-php/IyzipayBootstrap.php';
        if (is_readable($bootstrap)) {
            require_once $bootstrap;
            IyzipayBootstrap::init($vendorRoot . '/iyzico/iyzipay-php/src');
        }
    }

    if (!class_exists('Iyzipay\\Options')) {
        throw new RuntimeException(
            'iyzico SDK could not be loaded. Delete htdocs/vendor, re-extract vendor.zip, and confirm vendor/iyzico/iyzipay-php/ is present.'
        );
    }

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
    $rate = function_exists('zera_env') ? zera_env('IYZICO_USD_TRY_RATE') : getenv('IYZICO_USD_TRY_RATE');
    if ($rate !== false && is_numeric($rate) && (float) $rate > 0) {
        return (float) $rate;
    }
    return 34.0;
}

function format_try_amount(float $amount): string
{
    return number_format(round($amount, 2), 2, '.', '');
}

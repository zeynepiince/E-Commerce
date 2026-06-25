<?php

const SUPPORTED_LANGS = ["en", "tr"];
const DEFAULT_LANG = "en";

function detect_lang_from_uri(string $uriPath): ?string
{
    $path = trim($uriPath, "/");
    if ($path === "") return null;
    $parts = explode("/", $path);
    foreach ($parts as $part) {
        $candidate = strtolower(trim($part));
        if (in_array($candidate, SUPPORTED_LANGS, true)) {
            return $candidate;
        }
    }
    return null;
}

function get_current_lang(): string
{
    static $lang = null;
    if (is_string($lang)) return $lang;

    $queryLang = strtolower((string) ($_GET["lang"] ?? ""));
    $uriPath = parse_url((string) ($_SERVER["REQUEST_URI"] ?? ""), PHP_URL_PATH) ?: "";
    $uriLang = detect_lang_from_uri((string) $uriPath);
    $sessionLang = strtolower((string) ($_SESSION["lang"] ?? ""));

    if (in_array($queryLang, SUPPORTED_LANGS, true)) {
        $lang = $queryLang;
    } elseif (is_string($uriLang) && in_array($uriLang, SUPPORTED_LANGS, true)) {
        $lang = $uriLang;
    } elseif (in_array($sessionLang, SUPPORTED_LANGS, true)) {
        $lang = $sessionLang;
    } else {
        $lang = DEFAULT_LANG;
    }

    $_SESSION["lang"] = $lang;
    return $lang;
}

function load_locale(string $lang): array
{
    static $cache = [];
    if (isset($cache[$lang])) return $cache[$lang];
    $file = __DIR__ . "/locales/{$lang}.php";
    if (!file_exists($file)) {
        $cache[$lang] = [];
        return $cache[$lang];
    }
    $data = require $file;
    $cache[$lang] = is_array($data) ? $data : [];
    return $cache[$lang];
}

function t(string $key, ?string $fallback = null): string
{
    $lang = get_current_lang();
    $dict = load_locale($lang);
    $enDict = $lang === "en" ? $dict : load_locale("en");

    $value = $dict[$key] ?? ($enDict[$key] ?? null);
    if (is_string($value) && $value !== "") return $value;
    if (is_string($fallback)) return $fallback;
    return $key;
}

function site_base_path(): string
{
    $override = trim((string) (getenv('APP_BASE_PATH') ?: ''));
    if ($override !== '') {
        return rtrim(str_replace('\\', '/', $override), '/');
    }

    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    } else {
        $dir = rtrim($dir, '/');
    }

    // InfinityFree / shared hosting: SCRIPT_NAME bazen boş veya yanlış olabiliyor
    if ($dir === '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = rtrim(str_replace('\\', '/', (string) $_SERVER['DOCUMENT_ROOT']), '/');
        $appRoot = rtrim(str_replace('\\', '/', __DIR__), '/');
        if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
            $derived = substr($appRoot, strlen($docRoot));
            if ($derived !== '' && $derived !== '/') {
                $dir = rtrim($derived, '/');
            }
        }
    }

    return $dir;
}

/**
 * Root-absolute URL for static assets (CSS/JS/images).
 * Works when the app is deployed in a subdirectory (e.g. InfinityFree htdocs subfolder).
 */
function asset_url(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = site_base_path();
    if ($base === '') {
        return '/' . $path;
    }
    return $base . '/' . $path;
}

/** Full absolute URL (scheme + host + path) — en güvenilir asset yükleme yolu. */
function asset_full_url(string $path): string
{
    $scheme = (function_exists('zera_is_https') && zera_is_https()) ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . asset_url($path);
}

/** JSON API endpoint absolute URL (chatbot, auth, checkout, …). */
function api_url(string $file): string
{
    return asset_full_url($file);
}

/** InfinityFree-safe chat/feedback relay via index.php (never blocked). */
function zera_relay_url(string $action): string
{
    $lang = get_current_lang();
    $query = http_build_query([
        'zera_action' => $action,
        'lang' => $lang,
    ]);
    return asset_full_url('index.php') . '?' . $query;
}

/** Base href for relative links (assets/, auth.css, …). */
function site_base_href(): string
{
    $scheme = (function_exists('zera_is_https') && zera_is_https()) ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = site_base_path();
    return $scheme . '://' . $host . ($base !== '' ? $base . '/' : '/');
}

function localized_path(string $file, array $params = [], ?string $lang = null): string
{
    $targetLang = $lang ?: get_current_lang();
    $targetLang = in_array($targetLang, SUPPORTED_LANGS, true) ? $targetLang : DEFAULT_LANG;
    $base = site_base_path();
    $url = ($base !== "" ? $base : "") . "/" . ltrim($file, "/");
    if (isset($params["lang"])) {
        unset($params["lang"]);
    }
    $query = array_merge($params, ["lang" => $targetLang]);
    $url .= "?" . http_build_query($query);
    return $url;
}

function current_page_file(): string
{
    $script = (string) ($_SERVER["SCRIPT_NAME"] ?? "index.php");
    $base = basename($script);
    return $base !== "" ? $base : "index.php";
}


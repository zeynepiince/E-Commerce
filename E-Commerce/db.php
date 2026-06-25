<?php
/**
 * DB bağlantısı.
 *
 * Yapılandırma sırası (sonra gelen üstteki üzerine yazar):
 *   1. Aşağıdaki MAMP default'ları
 *   2. .env dosyası (E-Commerce/.env)
 *   3. Ortam değişkenleri (getenv) – DB_HOST, DB_PORT, DB_NAME, DB_USER,
 *      DB_PASS, DB_SOCKET, DB_CHARSET
 *
 * Üretim ortamı için: kopya çekip .env dosyasına gerçek değerleri koyun;
 * .env dosyası .gitignore'a eklendiği için commit edilmez.
 */

if (!function_exists('zera_load_dotenv')) {
    function zera_load_dotenv(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));
            if ((str_starts_with($val, '"') && str_ends_with($val, '"'))
                || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if ($key === '') {
                continue;
            }
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
            @putenv("{$key}={$val}");
        }
    }
}

if (!function_exists('zera_env')) {
    /** .env okuma — paylaşımlı hostingde getenv() tek başına yeterli olmayabilir. */
    function zera_env(string $key, ?string $default = null): ?string
    {
        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        $fromGetenv = getenv($key);
        if ($fromGetenv !== false && $fromGetenv !== '') {
            return (string) $fromGetenv;
        }
        if (isset($_SERVER[$key]) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

foreach (['.env', 'zera.env'] as $envFile) {
    zera_load_dotenv(__DIR__ . '/' . $envFile);
}

$cfg = [
    'host'    => zera_env('DB_HOST', 'localhost') ?? 'localhost',
    'port'    => zera_env('DB_PORT', '') ?? '',
    'name'    => zera_env('DB_NAME', 'chatbotv2_db') ?? 'chatbotv2_db',
    'user'    => zera_env('DB_USER', 'root') ?? 'root',
    'pass'    => zera_env('DB_PASS', 'root') ?? 'root',
    'socket'  => zera_env('DB_SOCKET', '') ?? '',
    'charset' => zera_env('DB_CHARSET', 'utf8mb4') ?? 'utf8mb4',
];

// DSN: tek bir yapıyla hem TCP (host+port) hem unix socket destekli
$dsnParts = ["dbname={$cfg['name']}", "charset={$cfg['charset']}"];
if ($cfg['socket'] !== '') {
    $dsnParts[] = "unix_socket={$cfg['socket']}";
} else {
    $dsnParts[] = "host={$cfg['host']}";
    if ($cfg['port'] !== '') {
        $dsnParts[] = "port={$cfg['port']}";
    }
}
$dsn = 'mysql:' . implode(';', $dsnParts);

try {
    $pdo = new PDO(
        $dsn,
        $cfg['user'],
        $cfg['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    // Tam connection string'i sızdırmamak için sadece kısa mesaj
    die("DB Connection failed: " . $e->getMessage());
}

require_once __DIR__ . '/database/SchemaService.php';
ensure_application_schema($pdo);

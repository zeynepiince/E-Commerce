<?php

/**
 * JSON POST relay for chat + feedback (InfinityFree-safe entry via index.php?zera_action=…).
 */

function zera_chat_relay_paths(string $file): array
{
    return [
        __DIR__ . '/' . $file,
        dirname(__DIR__) . '/' . $file,
        dirname(__DIR__) . '/chatbot/' . $file,
    ];
}

function zera_chat_relay_require(string $file): bool
{
    foreach (zera_chat_relay_paths($file) as $path) {
        if (is_readable($path)) {
            require $path;
            return true;
        }
    }
    return false;
}

function zera_chat_relay_boot(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    $booted = true;
    require_once __DIR__ . '/helpers.php';
    require_once __DIR__ . '/responses.php';
    require_once __DIR__ . '/actions.php';
    require_once __DIR__ . '/ai.php';
    require_once __DIR__ . '/intent.php';
    require_once __DIR__ . '/consistency.php';
}

function zera_handle_chat_api_request(): void
{
    $action = (string) ($_GET['zera_action'] ?? '');
    if ($action !== 'chat' && $action !== 'feedback') {
        return;
    }

    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['reply' => 'Method not allowed.', 'intent' => 'error']);
        exit;
    }

    csrf_require(true);

    $payload = zera_read_post_payload();

    try {
        if ($action === 'chat') {
            zera_chat_relay_boot();
            $data = $payload;
            if (!zera_chat_relay_require('serve_message.php')) {
                echo json_encode([
                    'reply' => function_exists('get_current_lang') && get_current_lang() === 'tr'
                        ? 'Chat modülü sunucuda bulunamadı. chatbot/serve_message.php dosyasını yükleyin.'
                        : 'Chat module missing on server. Upload chatbot/serve_message.php.',
                    'intent' => 'error',
                    'suggested_products' => [],
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            exit;
        }

        if ($action === 'feedback') {
            $data = $payload;
            if (!zera_chat_relay_require('serve_feedback.php')) {
                echo json_encode(['ok' => false, 'error' => 'module_missing']);
                exit;
            }
            exit;
        }
    } catch (Throwable $e) {
        error_log('zera chat relay: ' . $e->getMessage());
        $lang = function_exists('get_current_lang') ? get_current_lang() : 'en';
        echo json_encode([
            'reply' => $lang === 'tr'
                ? 'Geçici bir sunucu hatası oluştu. Lütfen tekrar dene.'
                : 'A temporary server error occurred. Please try again.',
            'intent' => 'error',
            'suggested_products' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

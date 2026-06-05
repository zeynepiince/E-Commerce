<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth/AuthService.php';
require_once __DIR__ . '/mail/PasswordResetService.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed', 'code' => 'method_not_allowed']);
    exit;
}

csrf_require(true);

$payload = $_POST;
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$action = (string) ($payload['action'] ?? '');
$lang = (string) ($payload['lang'] ?? get_current_lang());

if ($action === 'signin') {
    $result = auth_process_signin(
        $pdo,
        (string) ($payload['email'] ?? ''),
        (string) ($payload['password'] ?? '')
    );
} elseif ($action === 'join') {
    $result = auth_process_join(
        $pdo,
        (string) ($payload['name'] ?? ''),
        (string) ($payload['email'] ?? ''),
        (string) ($payload['password'] ?? ''),
        (string) ($payload['confirm'] ?? ''),
        $lang
    );
} elseif ($action === 'forgot') {
    $email = trim((string) ($payload['email'] ?? ''));
    if (password_reset_rate_limited()) {
        $result = [
            'success' => false,
            'message' => t('auth.forgot_rate_limit', 'Too many reset requests. Please try again in an hour.'),
            'message_type' => 'error',
        ];
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = [
            'success' => false,
            'message' => t('auth.error_invalid_email', 'Please enter a valid email address.'),
            'message_type' => 'error',
        ];
    } else {
        $reset = request_password_reset($pdo, $email, $lang);
        if (!$reset['mail_configured']) {
            $result = [
                'success' => false,
                'message' => t('auth.forgot_mail_disabled', 'Password reset email is not configured on this server.'),
                'message_type' => 'error',
            ];
        } elseif (!$reset['sent']) {
            $result = [
                'success' => false,
                'message' => t('auth.forgot_send_failed', 'We could not send the reset email. Please try again later.'),
                'message_type' => 'error',
            ];
        } else {
            $result = [
                'success' => true,
                'message' => t('auth.forgot_sent', 'If an account exists for this email, we sent password reset instructions.'),
                'message_type' => 'success',
            ];
        }
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action', 'code' => 'invalid_action']);
    exit;
}

$response = ['success' => (bool) $result['success']];
if (!empty($result['message'])) {
    $response['message'] = $result['message'];
}
if (!empty($result['message_type'])) {
    $response['message_type'] = $result['message_type'];
}
if (!empty($result['active_tab'])) {
    $response['active_tab'] = $result['active_tab'];
}
if (!empty($result['user'])) {
    $response['user'] = $result['user'];
}

if (!$result['success']) {
    http_response_code(422);
}

echo json_encode($response);

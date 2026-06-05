<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth/OAuthService.php';

$error = (string) ($_GET['error'] ?? '');
if ($error !== '') {
    $message = t('auth.oauth_denied', 'Sign-in was cancelled.');
    header('Location: ' . localized_path('auth.php', ['oauth_error' => $message]));
    exit;
}

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');

try {
    if ($code === '' || $state === '') {
        throw new RuntimeException(t('auth.oauth_missing_code', 'Sign-in could not be completed.'));
    }
    $returnUrl = oauth_complete_callback($pdo, $code, $state);
    header('Location: ' . $returnUrl);
    exit;
} catch (Throwable $e) {
    $message = $e->getMessage();
    header('Location: ' . localized_path('auth.php', ['oauth_error' => $message]));
    exit;
}

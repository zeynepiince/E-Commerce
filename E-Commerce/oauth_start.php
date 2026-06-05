<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth/OAuthService.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . auth_safe_return_url($_GET['return'] ?? 'index.php'));
    exit;
}

$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$returnUrl = auth_safe_return_url($_GET['return'] ?? 'index.php');

oauth_begin($provider, $returnUrl);

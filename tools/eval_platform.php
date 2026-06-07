#!/usr/bin/env php
<?php
/**
 * Evaluate platform services: auth, OAuth, user prefs, checkout/payments, admin, orders.
 *
 * Usage:
 *   php tools/eval_platform.php
 *   php tools/eval_platform.php --failures
 *   php tools/eval_platform.php --json
 *   php tools/eval_platform.php --save
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/tools/lib/eval_helpers.php';

$opts = getopt('', ['failures', 'json', 'save', 'help', 'no-db']);
if (isset($opts['help'])) {
    echo "Usage: php tools/eval_platform.php [--failures] [--json] [--save] [--no-db]\n";
    exit(0);
}

$datasetPath = $root . '/docs/platform_test_set.json';
if (!is_readable($datasetPath)) {
    fwrite(STDERR, "Dataset not found: {$datasetPath}\n");
    exit(1);
}

$dataset = json_decode((string) file_get_contents($datasetPath), true);
if (!is_array($dataset)) {
    fwrite(STDERR, "Invalid JSON in platform_test_set.json\n");
    exit(1);
}

$ecRoot = $root . '/E-Commerce';
require_once $ecRoot . '/i18n.php';
require_once $ecRoot . '/functions.php';
require_once $ecRoot . '/security/Security.php';
require_once $ecRoot . '/auth/AuthService.php';
require_once $ecRoot . '/auth/OAuthService.php';
require_once $ecRoot . '/user/UserPreferencesService.php';
require_once $ecRoot . '/orders/OrderStatusService.php';
require_once $ecRoot . '/payments/bootstrap.php';
require_once $ecRoot . '/payments/IyzicoService.php';
require_once $ecRoot . '/payments/OrderService.php';

if (session_status() === PHP_SESSION_NONE) {
    zera_init_session();
}

$report = eval_report_new();
$dbAvailable = false;
global $pdo;

if (!isset($opts['no-db'])) {
    try {
        if (!($pdo instanceof PDO)) {
            require_once $ecRoot . '/db.php';
        }
        $pdo->query('SELECT 1');
        $dbAvailable = true;
    } catch (Throwable $e) {
        $dbAvailable = false;
    }
}

// ── JSON-driven suites ───────────────────────────────────────────────────────

foreach ($dataset['auth_return_url'] ?? [] as $case) {
    $id = (string) ($case['id'] ?? 'auth_url');
    $input = array_key_exists('input', $case) ? (string) $case['input'] : null;
    $expect = (string) ($case['expect'] ?? 'index.php');
    $actual = auth_safe_return_url($input);
    eval_assert($report, $actual === $expect, 'auth', $id, "expected {$expect}, got {$actual}");
}

foreach ($dataset['order_payment_normalize'] ?? [] as $case) {
    $id = (string) ($case['id'] ?? 'pay_norm');
    $input = (string) ($case['input'] ?? '');
    $expect = (string) ($case['expect'] ?? '');
    $actual = normalize_order_payment_status($input);
    eval_assert($report, $actual === $expect, 'orders', $id, "expected {$expect}, got {$actual}");
}

foreach ($dataset['order_display_status'] ?? [] as $case) {
    $id = (string) ($case['id'] ?? 'disp');
    $fulfillment = (string) ($case['fulfillment'] ?? 'pending');
    $payment = (string) ($case['payment'] ?? 'paid');
    $expect = (string) ($case['expect'] ?? '');
    $actual = resolve_order_display_status_key($fulfillment, $payment);
    eval_assert($report, $actual === $expect, 'orders', $id, "expected {$expect}, got {$actual}");
}

$transitions = order_status_transitions();
foreach ($dataset['order_transitions'] ?? [] as $case) {
    $id = (string) ($case['id'] ?? 'tr');
    $from = (string) ($case['from'] ?? '');
    $to = (string) ($case['to'] ?? '');
    $allowed = (bool) ($case['allowed'] ?? false);
    $next = $transitions[$from] ?? [];
    $actual = in_array($to, $next, true);
    eval_assert($report, $actual === $allowed, 'admin', $id, "transition {$from}→{$to} allowed=" . ($allowed ? 'yes' : 'no'));
}

$envKeys = ['IYZICO_API_KEY', 'IYZICO_SECRET_KEY', 'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET', 'ADMIN_EMAIL'];
$envBackup = eval_backup_env($envKeys);

foreach ($dataset['payments_config'] ?? [] as $case) {
    $id = (string) ($case['id'] ?? 'iyz');
    eval_unset_env('IYZICO_API_KEY');
    eval_unset_env('IYZICO_SECRET_KEY');
    $apiKey = (string) ($case['api_key'] ?? '');
    $secretKey = (string) ($case['secret_key'] ?? '');
    if ($apiKey !== '') {
        eval_set_env('IYZICO_API_KEY', $apiKey);
    }
    if ($secretKey !== '') {
        eval_set_env('IYZICO_SECRET_KEY', $secretKey);
    }
    $expect = (bool) ($case['expect_configured'] ?? false);
    $actual = iyzico_is_configured();
    eval_assert($report, $actual === $expect, 'payments', $id, 'iyzico_is_configured=' . ($actual ? 'true' : 'false'));
}

eval_restore_env($envBackup);

// ── Auth service validation (no persistent user writes) ────────────────────────

if ($dbAvailable && $pdo instanceof PDO) {
    $emptySignin = auth_process_signin($pdo, '', '');
    eval_assert(
        $report,
        ($emptySignin['success'] ?? true) === false,
        'auth',
        'signin_empty',
        'empty credentials should fail'
    );

    $shortJoin = auth_process_join($pdo, 'Eval User', 'eval-short@example.com', 'short', 'short', 'en');
    eval_assert(
        $report,
        ($shortJoin['success'] ?? true) === false && (($shortJoin['active_tab'] ?? '') === 'join'),
        'auth',
        'join_password_min',
        'short password should fail join'
    );

    $mismatchJoin = auth_process_join($pdo, 'Eval User', 'eval-mismatch@example.com', 'password123', 'password999', 'en');
    eval_assert(
        $report,
        ($mismatchJoin['success'] ?? true) === false,
        'auth',
        'join_password_mismatch',
        'password mismatch should fail join'
    );

    $badEmailJoin = auth_process_join($pdo, 'Eval User', 'not-an-email', 'password123', 'password123', 'en');
    eval_assert(
        $report,
        ($badEmailJoin['success'] ?? true) === false,
        'auth',
        'join_invalid_email',
        'invalid email should fail join'
    );
} else {
    eval_skip($report, 'auth', 'signin_empty', 'database unavailable');
    eval_skip($report, 'auth', 'join_password_min', 'database unavailable');
    eval_skip($report, 'auth', 'join_password_mismatch', 'database unavailable');
    eval_skip($report, 'auth', 'join_invalid_email', 'database unavailable');
}

// ── OAuth provider toggles ─────────────────────────────────────────────────────

$oauthBackup = eval_backup_env(['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'FACEBOOK_APP_ID', 'FACEBOOK_APP_SECRET']);
eval_unset_env('GOOGLE_CLIENT_ID');
eval_unset_env('GOOGLE_CLIENT_SECRET');
eval_unset_env('FACEBOOK_APP_ID');
eval_unset_env('FACEBOOK_APP_SECRET');

eval_assert($report, oauth_google_enabled() === false, 'oauth', 'google_disabled', 'google should be off without env');
eval_assert($report, oauth_facebook_enabled() === false, 'oauth', 'facebook_disabled', 'facebook should be off without env');
eval_assert($report, oauth_start_url('google', 'index.php') === null, 'oauth', 'google_start_null', 'start URL null when disabled');
eval_assert($report, oauth_provider_enabled('unknown') === false, 'oauth', 'unknown_provider', 'unknown provider disabled');

eval_set_env('GOOGLE_CLIENT_ID', 'test-client-id');
eval_set_env('GOOGLE_CLIENT_SECRET', 'test-client-secret');
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['HTTPS'] = 'off';

eval_assert($report, oauth_google_enabled() === true, 'oauth', 'google_enabled', 'google on with credentials');
$googleStart = oauth_start_url('google', 'products.php');
eval_assert(
    $report,
    is_string($googleStart) && str_contains($googleStart, 'oauth_start.php') && str_contains($googleStart, 'provider=google'),
    'oauth',
    'google_start_url',
    'start URL should point to oauth_start.php'
);

eval_restore_env($oauthBackup);

// ── User notification preferences (profile) ───────────────────────────────────

if ($dbAvailable && $pdo instanceof PDO) {
    $prefUserId = null;
    try {
        $prefStmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, email_notifications)
             VALUES (?, ?, ?, 1)'
        );
        $prefEmail = 'eval-prefs-' . bin2hex(random_bytes(4)) . '@example.com';
        $prefStmt->execute(['Eval Prefs', $prefEmail, password_hash('eval-prefs-pass', PASSWORD_DEFAULT)]);
        $prefUserId = (int) $pdo->lastInsertId();

        user_prefs_save_email_notifications($pdo, $prefUserId, true);
        eval_assert(
            $report,
            user_prefs_get_email_notifications($pdo, $prefUserId) === true,
            'user_prefs',
            'save_preferences',
            'email_notifications flag should persist on users row'
        );

        user_prefs_save_email_notifications($pdo, $prefUserId, false);
        eval_assert(
            $report,
            user_prefs_get_email_notifications($pdo, $prefUserId) === false,
            'user_prefs',
            'clear_preferences',
            'email_notifications flag should update when cleared'
        );

        $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$prefUserId]);
    } catch (Throwable $e) {
        if ($prefUserId) {
            $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$prefUserId]);
        }
        eval_skip($report, 'user_prefs', 'save_preferences', 'preference test failed: ' . $e->getMessage());
        eval_skip($report, 'user_prefs', 'clear_preferences', 'preference test failed');
    }
} else {
    eval_skip($report, 'user_prefs', 'save_preferences', 'database unavailable');
    eval_skip($report, 'user_prefs', 'clear_preferences', 'database unavailable');
}

// ── Checkout / cart normalization ──────────────────────────────────────────────

if ($dbAvailable && $pdo instanceof PDO) {
    $emptyCart = normalize_cart_lines($pdo, []);
    eval_assert(
        $report,
        ($emptyCart['total_usd'] ?? -1) === 0.0 && ($emptyCart['lines'] ?? null) === [],
        'checkout',
        'normalize_empty_cart',
        'empty cart should normalize to zero lines'
    );

    $multiQty = normalize_cart_lines($pdo, [
        ['id' => 0, 'name' => 'Ghost Product', 'price' => 12.5, 'qty' => 2],
    ]);
    eval_assert(
        $report,
        ($multiQty['total_usd'] ?? 0) === 25.0 && count($multiQty['lines'] ?? []) === 1,
        'checkout',
        'normalize_totals',
        'line totals should multiply qty × price'
    );

    $productId = (int) $pdo->query('SELECT product_id FROM products ORDER BY product_id ASC LIMIT 1')->fetchColumn();
    if ($productId > 0) {
        $row = $pdo->prepare('SELECT name, stock_quantity FROM products WHERE product_id = ? LIMIT 1');
        $row->execute([$productId]);
        $product = $row->fetch(PDO::FETCH_ASSOC);
        if (is_array($product)) {
            $stock = max(1, (int) ($product['stock_quantity'] ?? 1));
            try {
                $created = create_awaiting_payment_order($pdo, 1, [
                    ['id' => $productId, 'name' => (string) $product['name'], 'price' => 9.99, 'qty' => 1],
                ], [
                    'full_name' => 'Eval Checkout',
                    'email' => 'eval-checkout@example.com',
                    'phone' => '+905551112233',
                    'address' => 'Test Address',
                    'city' => 'Istanbul',
                    'postal_code' => '34000',
                ]);
                $orderId = (int) ($created['order_id'] ?? 0);
                eval_assert(
                    $report,
                    $orderId > 0 && ($created['total_usd'] ?? 0) > 0,
                    'checkout',
                    'create_awaiting_payment',
                    'should create awaiting_payment order'
                );
                $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
                $pdo->prepare('DELETE FROM orders WHERE order_id = ?')->execute([$orderId]);
            } catch (Throwable $e) {
                eval_fail($report, 'checkout', 'create_awaiting_payment', $e->getMessage());
            }
        }
    } else {
        eval_skip($report, 'checkout', 'create_awaiting_payment', 'no products in catalog');
    }

    try {
        create_awaiting_payment_order($pdo, 1, [], []);
        eval_fail($report, 'checkout', 'empty_cart_rejected', 'empty cart should throw');
    } catch (RuntimeException $e) {
        eval_assert($report, str_contains($e->getMessage(), 'Empty cart'), 'checkout', 'empty_cart_rejected', 'empty cart throws RuntimeException');
    }
} else {
    eval_skip($report, 'checkout', 'normalize_empty_cart', 'database unavailable');
    eval_skip($report, 'checkout', 'normalize_totals', 'database unavailable');
    eval_skip($report, 'checkout', 'create_awaiting_payment', 'database unavailable');
    eval_skip($report, 'checkout', 'empty_cart_rejected', 'database unavailable');
}

// ── Payments helpers ───────────────────────────────────────────────────────────

eval_assert(
    $report,
    iyzico_usd_to_try_rate() >= 1.0,
    'payments',
    'usd_try_rate',
    'USD→TRY rate should be a positive number'
);

// ── Admin access rules ─────────────────────────────────────────────────────────

$adminBackup = eval_backup_env(['ADMIN_EMAIL']);
eval_set_env('ADMIN_EMAIL', 'admin@example.com');

$_SESSION = [];
$_SESSION['user_id'] = 1;
$_SESSION['user_email'] = 'user@example.com';
eval_assert($report, is_admin_user() === false, 'admin', 'non_admin_denied', 'non-admin session should fail');

$_SESSION['user_email'] = 'admin@example.com';
eval_assert($report, is_admin_user() === true, 'admin', 'admin_allowed', 'matching ADMIN_EMAIL should pass');

unset($_SESSION['user_id']);
eval_assert($report, is_admin_user() === false, 'admin', 'guest_denied', 'guest should not be admin');

eval_restore_env($adminBackup);

// ── Admin order updates (DB) ───────────────────────────────────────────────────

if ($dbAvailable && $pdo instanceof PDO) {
    $missing = update_order_status($pdo, 999999999, 'shipped');
    eval_assert(
        $report,
        ($missing['success'] ?? true) === false && (($missing['error'] ?? '') === 'Order not found'),
        'admin',
        'update_missing_order',
        'missing order should return Order not found'
    );

    $invalidStatus = update_order_status($pdo, 1, 'invalid_status');
    eval_assert(
        $report,
        ($invalidStatus['success'] ?? true) === false && (($invalidStatus['error'] ?? '') === 'Invalid status'),
        'admin',
        'update_invalid_status',
        'invalid status should be rejected'
    );
} else {
    eval_skip($report, 'admin', 'update_missing_order', 'database unavailable');
    eval_skip($report, 'admin', 'update_invalid_status', 'database unavailable');
}

// ── Security / CSRF ──────────────────────────────────────────────────────────

$token = csrf_token();
eval_assert($report, is_string($token) && strlen($token) === 64, 'security', 'csrf_token_length', 'csrf token should be 64 hex chars');

$_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
eval_assert($report, csrf_verify($token) === true, 'security', 'csrf_verify_valid', 'valid CSRF token should verify');

eval_assert($report, csrf_verify('invalid-token') === false, 'security', 'csrf_verify_invalid', 'invalid CSRF token should fail');

csrf_rotate();
$newToken = csrf_token();
eval_assert($report, $newToken !== $token, 'security', 'csrf_rotate', 'csrf_rotate should change token');

// ── Output ───────────────────────────────────────────────────────────────────

$summary = [
    'passed' => $report['passed'],
    'failed' => count($report['failed']),
    'skipped' => count($report['skipped']),
    'db_available' => $dbAvailable,
    'failures' => $report['failed'],
    'skipped_cases' => $report['skipped'],
];

if (isset($opts['save'])) {
    $outPath = $root . '/docs/eval_platform_results.json';
    file_put_contents(
        $outPath,
        json_encode(
            ['generated_at' => gmdate('c'), 'summary' => $summary],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ) . "\n"
    );
}

if (isset($opts['json'])) {
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(eval_exit_code($report));
}

eval_print_report('ZERA Platform Evaluation', $report, isset($opts['failures']));
echo $dbAvailable ? "Database: connected (integration tests ran)\n" : "Database: unavailable (unit tests only; use without --no-db when MySQL is up)\n";
echo eval_exit_code($report) === 0 ? "Overall: PASS\n" : "Overall: FAIL\n";

exit(eval_exit_code($report));

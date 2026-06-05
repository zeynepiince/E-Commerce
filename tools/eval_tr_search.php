#!/usr/bin/env php
<?php
/**
 * Turkish product search synonym / matching evaluation.
 *
 * Usage:
 *   php tools/eval_tr_search.php
 *   php tools/eval_tr_search.php --failures
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$datasetPath = $root . '/docs/tr_search_test_set.json';
$ecommerce = $root . '/E-Commerce';

require_once $ecommerce . '/db.php';
require_once $ecommerce . '/chatbot/helpers.php';
require_once $ecommerce . '/chatbot/tr_synonyms.php';
require_once $ecommerce . '/chatbot/intent.php';
require_once $ecommerce . '/chatbot/actions.php';

$opts = getopt('', ['failures', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php tools/eval_tr_search.php [--failures]\n";
    exit(0);
}

$data = json_decode((string) file_get_contents($datasetPath), true);
if (!is_array($data) || empty($data['cases'])) {
    fwrite(STDERR, "Invalid tr_search_test_set.json\n");
    exit(1);
}

$passed = 0;
$failed = 0;
$failures = [];

foreach ($data['cases'] as $case) {
    $id = (string) ($case['id'] ?? '?');
    $query = (string) ($case['query'] ?? '');
    $entities = extract_entities($query);
    if (!empty($case['session_memory_entities']) && is_array($case['session_memory_entities'])) {
        $memory = ['entities' => $case['session_memory_entities']];
        $userProfile = ['prefers_budget' => true, 'category_interest' => null];
        $ctx = apply_product_search_session_context($entities, $query, 'product_search', $memory, $userProfile);
        $entities = $ctx['entities'];
    }
    $entities = expand_entities_for_product_search($entities);
    $products = filter_products_for_entities(search_products_advanced($pdo, $entities, 4, 0), $entities);
    $errors = [];

    if (!empty($case['expect_product_type']) && to_lower((string) ($entities['product_type'] ?? '')) !== to_lower((string) $case['expect_product_type'])) {
        $errors[] = 'expected product_type ' . $case['expect_product_type'] . ', got ' . ($entities['product_type'] ?? 'null');
    }
    if (!empty($case['expect_audience']) && (string) ($entities['audience'] ?? '') !== (string) $case['expect_audience']) {
        $errors[] = 'expected audience ' . $case['expect_audience'] . ', got ' . ($entities['audience'] ?? 'null');
    }
    if (!empty($case['expect_size']) && strtoupper((string) ($entities['size'] ?? '')) !== strtoupper((string) $case['expect_size'])) {
        $errors[] = 'expected size ' . $case['expect_size'] . ', got ' . ($entities['size'] ?? 'null');
    }
    if (!empty($case['expect_brand']) && to_lower((string) ($entities['brand'] ?? '')) !== to_lower((string) $case['expect_brand'])) {
        $errors[] = 'expected brand ' . $case['expect_brand'] . ', got ' . ($entities['brand'] ?? 'null');
    }
    if (!empty($case['expect_sort_by']) && (string) ($entities['sort_by'] ?? '') !== (string) $case['expect_sort_by']) {
        $errors[] = 'expected sort_by ' . $case['expect_sort_by'] . ', got ' . ($entities['sort_by'] ?? 'null');
    }

    $min = (int) ($case['min_results'] ?? 1);
    if (count($products) < $min) {
        $errors[] = 'expected at least ' . $min . ' product(s), got ' . count($products);
    }

    if (!empty($case['expect_any_sub']) && is_array($case['expect_any_sub'])) {
        $subs = array_map(static fn($s) => to_lower((string) $s), $case['expect_any_sub']);
        $hit = false;
        foreach ($products as $p) {
            $sub = to_lower((string) ($p['sub_category'] ?? ''));
            if (in_array($sub, $subs, true)) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            $names = array_map(static fn($p) => ($p['name'] ?? '?') . ' [' . ($p['sub_category'] ?? '') . ']', $products);
            $errors[] = 'expected sub_category in [' . implode(', ', $case['expect_any_sub']) . '], got: ' . implode('; ', $names);
        }
    }

    if (!empty($case['expect_no_max_price'])) {
        if (is_numeric($entities['max_price'] ?? null)) {
            $errors[] = 'expected no inherited max_price, got ' . $entities['max_price'];
        }
    }

    if (isset($case['expect_max_price_usd']) && is_numeric($case['expect_max_price_usd'])) {
        $got = (float) ($entities['max_price'] ?? 0);
        $want = (float) $case['expect_max_price_usd'];
        if (abs($got - $want) > 0.25) {
            $errors[] = "expected max_price_usd ~= {$want}, got {$got}";
        }
    }

    if (isset($case['max_product_price']) && is_numeric($case['max_product_price'])) {
        $cap = (float) $case['max_product_price'];
        foreach ($products as $p) {
            if ((float) ($p['price'] ?? 0) > $cap) {
                $errors[] = 'product price ' . ($p['price'] ?? '?') . ' exceeds cap ' . $cap . ' (' . ($p['name'] ?? '') . ')';
            }
        }
    }

    if (!empty($case['reject_name_contains']) && is_array($case['reject_name_contains'])) {
        foreach ($products as $p) {
            $name = (string) ($p['name'] ?? '');
            foreach ($case['reject_name_contains'] as $bad) {
                if ($bad !== '' && mb_stripos($name, (string) $bad, 0, 'UTF-8') !== false) {
                    $errors[] = "unexpected product name contains '{$bad}': {$name}";
                }
            }
        }
    }

    if ($errors === []) {
        $passed++;
    } else {
        $failed++;
        $failures[] = ['id' => $id, 'query' => $query, 'errors' => $errors];
    }
}

$total = $passed + $failed;
$rate = $total > 0 ? round(100 * $passed / $total, 2) : 0;

echo "Turkish Product Search Evaluation\n";
echo str_repeat('=', 60) . "\n";
echo "Cases: {$total}\n";
echo "Pass rate: {$rate}% ({$passed}/{$total})\n\n";

if (isset($opts['failures']) && $failures !== []) {
    foreach ($failures as $f) {
        echo $f['id'] . ' FAIL  ' . $f['query'] . "\n";
        foreach ($f['errors'] as $err) {
            echo "  - {$err}\n";
        }
    }
}

exit($failed > 0 ? 1 : 0);

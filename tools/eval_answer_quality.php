#!/usr/bin/env php
<?php
/**
 * Answer quality evaluation: DB/policy grounding + LLM guardrail rejection rate.
 *
 * Usage:
 *   php tools/eval_answer_quality.php
 *   php tools/eval_answer_quality.php --failures --save
 *   php tools/eval_answer_quality.php --json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$dialoguePath = $root . '/docs/dialogue_test_set.json';
$qualityPath = $root . '/docs/answer_quality_test_set.json';
$ecommerce = $root . '/E-Commerce';

require_once $ecommerce . '/db.php';
require_once $ecommerce . '/chatbot/helpers.php';
require_once $ecommerce . '/chatbot/responses.php';
require_once $ecommerce . '/chatbot/actions.php';
require_once $ecommerce . '/chatbot/intent.php';
require_once $ecommerce . '/chatbot/ai.php';
require_once $ecommerce . '/chatbot/answer_quality.php';

$opts = getopt('', ['failures', 'save', 'json', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php tools/eval_answer_quality.php [--failures] [--save] [--json]\n";
    exit(0);
}

$policyKnowledge = load_policy_knowledge();
$failures = [];
$byCategory = [
    'stock_grounding' => ['total' => 0, 'passed' => 0],
    'size_grounding' => ['total' => 0, 'passed' => 0],
    'policy_grounding' => ['total' => 0, 'passed' => 0],
    'price_grounding' => ['total' => 0, 'passed' => 0],
    'guardrail_normal' => ['total' => 0, 'passed' => 0, 'rejected' => 0, 'traps' => 0],
    'guardrail_strict' => ['total' => 0, 'passed' => 0, 'rejected' => 0, 'traps' => 0],
];

function bump_category(array &$byCategory, string $key, bool $passed): void
{
    if (!isset($byCategory[$key])) {
        $byCategory[$key] = ['total' => 0, 'passed' => 0];
    }
    $byCategory[$key]['total']++;
    if ($passed) {
        $byCategory[$key]['passed']++;
    }
}

// --- Dialogue scenarios with grounding expectations ---
$dialogueChecks = 0;
$dialoguePassed = 0;
$dialogueRows = [];

if (is_readable($dialoguePath)) {
    $dialogueData = json_decode((string) file_get_contents($dialoguePath), true);
    if (is_array($dialogueData['scenarios'] ?? null)) {
        foreach ($dialogueData['scenarios'] as $scenario) {
            $scenarioId = (string) ($scenario['id'] ?? '');
            $lang = (string) ($scenario['lang'] ?? 'en');
            $chatUserId = array_key_exists('user_id', $scenario) ? ($scenario['user_id'] !== null ? (int) $scenario['user_id'] : null) : null;
            $memory = [];
            $userProfile = ['prefers_budget' => false, 'category_interest' => null];

            foreach ($scenario['turns'] ?? [] as $turnIndex => $turn) {
                $userMessage = trim((string) ($turn['user'] ?? ''));
                $grounding = is_array($turn['expect']['grounding'] ?? null) ? $turn['expect']['grounding'] : null;
                if ($userMessage === '' || $grounding === null) {
                    if ($userMessage !== '') {
                        simulate_rule_based_turn($pdo, $userMessage, $lang, $memory, $userProfile, $chatUserId);
                    }
                    continue;
                }

                $result = simulate_rule_based_turn($pdo, $userMessage, $lang, $memory, $userProfile, $chatUserId);
                $eval = evaluate_grounding_expectations($pdo, $grounding, $result, $lang, $policyKnowledge, $memory);
                $dialogueChecks++;
                if ($eval['passed']) {
                    $dialoguePassed++;
                } else {
                    $failures[] = [
                        'category' => 'dialogue_grounding',
                        'id' => $scenarioId . ':turn' . ($turnIndex + 1),
                        'user' => $userMessage,
                        'errors' => $eval['errors'],
                        'checks' => $eval['checks'],
                    ];
                }

                foreach ($eval['checks'] as $checkName => $ok) {
                    if ($checkName === 'stock_matches_db') {
                        bump_category($byCategory, 'stock_grounding', $ok);
                    } elseif ($checkName === 'sizes_match_product') {
                        bump_category($byCategory, 'size_grounding', $ok);
                    } elseif ($checkName === 'policy_grounded') {
                        bump_category($byCategory, 'policy_grounding', $ok);
                    } elseif ($checkName === 'prices_match_products') {
                        bump_category($byCategory, 'price_grounding', $ok);
                    }
                }

                $dialogueRows[] = [
                    'scenario' => $scenarioId,
                    'turn' => $turnIndex + 1,
                    'user' => $userMessage,
                    'passed' => $eval['passed'],
                    'checks' => $eval['checks'],
                ];
            }
        }
    }
}

// --- Standalone policy probes ---
$policyRows = [];
if (is_readable($qualityPath)) {
    $qualityData = json_decode((string) file_get_contents($qualityPath), true);
    $policyProbes = is_array($qualityData['policy_probes'] ?? null) ? $qualityData['policy_probes'] : [];

    foreach ($policyProbes as $probe) {
        $id = (string) ($probe['id'] ?? '');
        $user = (string) ($probe['user'] ?? '');
        $lang = (string) ($probe['lang'] ?? 'en');
        $intent = (string) ($probe['intent'] ?? '');
        if ($user === '' || $intent === '') {
            continue;
        }

        $memory = [];
        $userProfile = ['prefers_budget' => false, 'category_interest' => null];
        $result = simulate_rule_based_turn($pdo, $user, $lang, $memory, $userProfile, null);
        $eval = evaluate_grounding_expectations(
            $pdo,
            ['policy_intent' => $intent, 'policy_min_score' => 0.15],
            $result,
            $lang,
            $policyKnowledge,
            $memory
        );

        bump_category($byCategory, 'policy_grounding', $eval['passed']);
        if (!$eval['passed']) {
            $failures[] = [
                'category' => 'policy_probe',
                'id' => $id,
                'user' => $user,
                'errors' => $eval['errors'],
            ];
        }

        $policyRows[] = [
            'id' => $id,
            'user' => $user,
            'intent' => $result['intent'],
            'passed' => $eval['passed'],
            'reply_excerpt' => mb_substr($result['reply'], 0, 100, 'UTF-8'),
        ];
    }

    // --- Guardrail synthetic cases ---
    $guardrailRows = [];
    $guardrailCases = is_array($qualityData['guardrail_cases'] ?? null) ? $qualityData['guardrail_cases'] : [];
    foreach ($guardrailCases as $case) {
        $id = (string) ($case['id'] ?? '');
        $reply = (string) ($case['reply'] ?? '');
        $intent = (string) ($case['intent'] ?? 'general');
        $products = is_array($case['products'] ?? null) ? $case['products'] : [];
        $expectAccepted = !empty($case['expect_accepted']);
        $guardrail = (string) ($case['guardrail'] ?? 'normal');
        $catKey = $guardrail === 'strict' ? 'guardrail_strict' : 'guardrail_normal';

        $eval = evaluate_guardrail_case($reply, $intent, $products, $expectAccepted, $guardrail);
        $byCategory[$catKey]['total'] = ($byCategory[$catKey]['total'] ?? 0) + 1;
        if ($eval['accepted'] === false) {
            $byCategory[$catKey]['rejected'] = ($byCategory[$catKey]['rejected'] ?? 0) + 1;
        }
        if ($expectAccepted === false) {
            $byCategory[$catKey]['traps'] = ($byCategory[$catKey]['traps'] ?? 0) + 1;
        }
        if ($eval['passed']) {
            $byCategory[$catKey]['passed'] = ($byCategory[$catKey]['passed'] ?? 0) + 1;
        } else {
            $failures[] = [
                'category' => $catKey,
                'id' => $id,
                'label' => (string) ($case['label'] ?? ''),
                'errors' => ['expected accepted=' . ($expectAccepted ? 'true' : 'false') . ' got=' . ($eval['accepted'] ? 'true' : 'false')],
            ];
        }

        $guardrailRows[] = [
            'id' => $id,
            'guardrail' => $guardrail,
            'expect_accepted' => $expectAccepted,
            'accepted' => $eval['accepted'],
            'passed' => $eval['passed'],
        ];
    }

    // --- Response consistency probes ---
    $consistencyRows = [];
    $consistencyProbes = is_array($qualityData['consistency_probes'] ?? null) ? $qualityData['consistency_probes'] : [];
    foreach ($consistencyProbes as $probe) {
        $id = (string) ($probe['id'] ?? '');
        $user = (string) ($probe['user'] ?? '');
        $lang = (string) ($probe['lang'] ?? 'en');
        if ($user === '') {
            continue;
        }

        $memory = [];
        $userProfile = ['prefers_budget' => false, 'category_interest' => null];
        $result = simulate_rule_based_turn($pdo, $user, $lang, $memory, $userProfile, null);
        $errors = [];

        if (!empty($probe['expect_intent']) && (string) $probe['expect_intent'] !== (string) ($result['intent'] ?? '')) {
            $errors[] = 'intent expected ' . $probe['expect_intent'] . ', got ' . ($result['intent'] ?? '');
        }
        if (!empty($probe['expect_no_product_list']) && reply_has_product_list((string) ($result['reply'] ?? ''))) {
            $errors[] = 'reply should not contain product price list';
        }
        if (!empty($probe['expect_no_suggested_products']) && !empty($result['suggested_products'])) {
            $errors[] = 'expected no suggested_products';
        }
        foreach ($probe['expect_reply_contains'] ?? [] as $needle) {
            if (!str_contains(function_exists('to_lower') ? to_lower((string) $result['reply']) : strtolower((string) $result['reply']), function_exists('to_lower') ? to_lower((string) $needle) : strtolower((string) $needle))) {
                $errors[] = "reply should contain '{$needle}'";
            }
        }
        if (isset($probe['expect_max_suggested_price']) && is_numeric($probe['expect_max_suggested_price'])) {
            foreach ($result['suggested_products'] ?? [] as $p) {
                $price = (float) ($p['price'] ?? 0);
                if ($price > (float) $probe['expect_max_suggested_price'] + 0.01) {
                    $errors[] = "suggested price {$price} exceeds max " . $probe['expect_max_suggested_price'];
                }
            }
        }
        foreach ($probe['reject_suggested_name_contains'] ?? [] as $bad) {
            foreach ($result['suggested_products'] ?? [] as $p) {
                if (stripos((string) ($p['name'] ?? ''), (string) $bad) !== false) {
                    $errors[] = "suggested product contains '{$bad}': " . ($p['name'] ?? '');
                }
            }
        }
        if (function_exists('is_intent_reply_mismatch') && is_intent_reply_mismatch((string) ($result['intent'] ?? ''), (string) ($result['reply'] ?? ''), is_array($result['suggested_products'] ?? null) ? $result['suggested_products'] : [])) {
            $errors[] = 'intent/reply/product mismatch detected';
        }

        $passed = $errors === [];
        bump_category($byCategory, 'consistency', $passed);
        if (!$passed) {
            $failures[] = [
                'category' => 'consistency',
                'id' => $id,
                'user' => $user,
                'errors' => $errors,
            ];
        }

        $consistencyRows[] = [
            'id' => $id,
            'user' => $user,
            'passed' => $passed,
            'intent' => $result['intent'] ?? '',
        ];
    }
}

$categoryRates = [];
$totalChecks = 0;
$totalPassed = 0;
foreach ($byCategory as $name => $stats) {
    if (($stats['total'] ?? 0) === 0) {
        continue;
    }
    $rate = $stats['passed'] / $stats['total'];
    $categoryRates[$name] = [
        'total' => $stats['total'],
        'passed' => $stats['passed'],
        'rate' => round($rate, 4),
    ];
    if (str_starts_with($name, 'guardrail_')) {
        $traps = (int) ($stats['traps'] ?? 0);
        $rejected = (int) ($stats['rejected'] ?? 0);
        $categoryRates[$name]['rejection_rate'] = $stats['total'] > 0 ? round($rejected / $stats['total'], 4) : 0.0;
        $categoryRates[$name]['trap_rejection_rate'] = $traps > 0
            ? round($rejected / $traps, 4)
            : null;
    }
    $totalChecks += $stats['total'];
    $totalPassed += $stats['passed'];
}

$taskSuccessRate = $totalChecks > 0 ? round($totalPassed / $totalChecks, 4) : 0.0;
$dialogueGroundingRate = $dialogueChecks > 0 ? round($dialoguePassed / $dialogueChecks, 4) : null;

$report = [
    'dataset_dialogue' => $dialoguePath,
    'dataset_quality' => $qualityPath,
    'evaluated_at' => date('c'),
    'pipeline' => 'rule-based replies + synthetic guardrail probes',
    'task_success_rate' => $taskSuccessRate,
    'answer_accuracy' => $taskSuccessRate,
    'total_checks' => $totalChecks,
    'total_passed' => $totalPassed,
    'dialogue_grounding_checks' => $dialogueChecks,
    'dialogue_grounding_passed' => $dialoguePassed,
    'dialogue_grounding_rate' => $dialogueGroundingRate,
    'by_category' => $categoryRates,
    'dialogue_grounding' => $dialogueRows,
    'policy_probes' => $policyRows ?? [],
    'guardrail_cases' => $guardrailRows ?? [],
    'consistency_probes' => $consistencyRows ?? [],
    'failures' => $failures,
];

if (isset($opts['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit($failures === [] ? 0 : 1);
}

echo "ZERA Answer Quality Evaluation\n";
echo str_repeat('=', 60) . "\n";
echo "Task success rate:  " . sprintf('%.2f%%', $taskSuccessRate * 100) . " ({$totalPassed}/{$totalChecks})\n";
if ($dialogueGroundingRate !== null) {
    echo "Dialogue grounding: " . sprintf('%.2f%%', $dialogueGroundingRate * 100) . " ({$dialoguePassed}/{$dialogueChecks})\n";
}
echo "\nBy category:\n";
foreach ($categoryRates as $name => $stats) {
    echo str_pad($name, 20) . sprintf('%.2f%%', $stats['rate'] * 100) . " ({$stats['passed']}/{$stats['total']})";
    if (isset($stats['trap_rejection_rate']) && $stats['trap_rejection_rate'] !== null) {
        echo "  trap_rejection=" . sprintf('%.2f%%', $stats['trap_rejection_rate'] * 100);
    }
    echo "\n";
}

if (isset($opts['failures']) && $failures !== []) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "  [{$f['category']}] {$f['id']}\n";
        foreach ($f['errors'] as $err) {
            echo "    - {$err}\n";
        }
    }
} elseif ($failures !== []) {
    echo "\n" . count($failures) . " failure(s). Run with --failures to list them.\n";
}

if (isset($opts['save'])) {
    $outPath = $root . '/docs/eval_answer_quality_results.json';
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nSaved report: {$outPath}\n";
}

exit($failures === [] ? 0 : 1);

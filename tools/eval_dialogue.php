#!/usr/bin/env php
<?php
/**
 * End-to-end multi-turn dialogue evaluation for ZERA chatbot.
 * Measures per-turn intent accuracy and reply assertion pass rate.
 *
 * Usage:
 *   php tools/eval_dialogue.php
 *   php tools/eval_dialogue.php --failures
 *   php tools/eval_dialogue.php --save
 *   php tools/eval_dialogue.php --json
 *   php tools/eval_dialogue.php --scenario dlg_01
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$datasetPath = $root . '/docs/dialogue_test_set.json';
$ecommerce = $root . '/E-Commerce';

if (!is_readable($datasetPath)) {
    fwrite(STDERR, "Dataset not found: {$datasetPath}\n");
    exit(1);
}

require_once $ecommerce . '/db.php';
require_once $ecommerce . '/chatbot/helpers.php';
require_once $ecommerce . '/chatbot/responses.php';
require_once $ecommerce . '/chatbot/actions.php';
require_once $ecommerce . '/chatbot/intent.php';
require_once $ecommerce . '/chatbot/answer_quality.php';

$opts = getopt('', ['failures', 'save', 'json', 'help', 'scenario:']);
$policyKnowledge = load_policy_knowledge();

if (isset($opts['help'])) {
    echo "Usage: php tools/eval_dialogue.php [--failures] [--save] [--json] [--scenario=dlg_01]\n";
    exit(0);
}

$raw = file_get_contents($datasetPath);
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['scenarios']) || !is_array($data['scenarios'])) {
    fwrite(STDERR, "Invalid dataset format in dialogue_test_set.json\n");
    exit(1);
}

/**
 * @return array{passed:bool, errors:array<int,string>}
 */
function evaluate_turn_expectations(
    array $expect,
    array $result,
    PDO $pdo,
    string $lang,
    array $policyKnowledge,
    array $memory
): array {
    $errors = [];

    if (isset($expect['intent']) && $result['intent'] !== (string) $expect['intent']) {
        $errors[] = "intent expected '{$expect['intent']}', got '{$result['intent']}'";
    }
    if (isset($expect['source']) && $result['source'] !== (string) $expect['source']) {
        $errors[] = "source expected '{$expect['source']}', got '{$result['source']}'";
    }
    if (isset($expect['min_suggested_products'])) {
        $min = (int) $expect['min_suggested_products'];
        $count = count($result['suggested_products'] ?? []);
        if ($count < $min) {
            $errors[] = "expected at least {$min} suggested product(s), got {$count}";
        }
    }
    if (!empty($expect['product_name_contains'])) {
        $first = $result['suggested_products'][0]['name'] ?? '';
        $needle = (string) $expect['product_name_contains'];
        if ($first === '' || mb_stripos((string) $first, $needle, 0, 'UTF-8') === false) {
            $errors[] = "first product name should contain '{$needle}', got '{$first}'";
        }
    }
    if (!empty($expect['reply_contains']) && is_array($expect['reply_contains'])) {
        foreach ($expect['reply_contains'] as $needle) {
            if (mb_stripos($result['reply'], (string) $needle, 0, 'UTF-8') === false) {
                $errors[] = "reply should contain '{$needle}'";
            }
        }
    }
    if (!empty($expect['reply_not_contains']) && is_array($expect['reply_not_contains'])) {
        foreach ($expect['reply_not_contains'] as $needle) {
            if (mb_stripos($result['reply'], (string) $needle, 0, 'UTF-8') !== false) {
                $errors[] = "reply should NOT contain '{$needle}'";
            }
        }
    }
    if (isset($expect['min_confidence']) && (float) $result['confidence'] < (float) $expect['min_confidence']) {
        $errors[] = "confidence expected >= {$expect['min_confidence']}, got {$result['confidence']}";
    }

    if (!empty($expect['grounding']) && is_array($expect['grounding'])) {
        $grounding = evaluate_grounding_expectations($pdo, $expect['grounding'], $result, $lang, $policyKnowledge, $memory);
        if (!$grounding['passed']) {
            foreach ($grounding['errors'] as $err) {
                $errors[] = $err;
            }
        }
    }

    return ['passed' => $errors === [], 'errors' => $errors];
}

$filterScenario = isset($opts['scenario']) ? (string) $opts['scenario'] : null;
$scenarios = $data['scenarios'];
if ($filterScenario !== null && $filterScenario !== '') {
    $scenarios = array_values(array_filter(
        $scenarios,
        static fn(array $s): bool => (string) ($s['id'] ?? '') === $filterScenario
    ));
    if ($scenarios === []) {
        fwrite(STDERR, "Scenario not found: {$filterScenario}\n");
        exit(1);
    }
}

$turnResults = [];
$scenarioResults = [];
$intentCorrect = 0;
$intentTotal = 0;
$replyCorrect = 0;
$replyTotal = 0;
$failures = [];

foreach ($scenarios as $scenario) {
    $scenarioId = (string) ($scenario['id'] ?? 'unknown');
    $scenarioName = (string) ($scenario['name'] ?? $scenarioId);
    $lang = (string) ($scenario['lang'] ?? 'en');
    $chatUserId = array_key_exists('user_id', $scenario) ? ($scenario['user_id'] !== null ? (int) $scenario['user_id'] : null) : null;
    $turns = $scenario['turns'] ?? [];
    if (!is_array($turns) || $turns === []) {
        continue;
    }

    $memory = [];
    $userProfile = ['prefers_budget' => false, 'category_interest' => null];
    $scenarioPassed = true;
    $scenarioTurnRows = [];

    foreach ($turns as $turnIndex => $turn) {
        $userMessage = trim((string) ($turn['user'] ?? ''));
        $expect = is_array($turn['expect'] ?? null) ? $turn['expect'] : [];
        if ($userMessage === '') {
            continue;
        }

        $result = simulate_rule_based_turn($pdo, $userMessage, $lang, $memory, $userProfile, $chatUserId);
        $evaluation = evaluate_turn_expectations($expect, $result, $pdo, $lang, $policyKnowledge, $memory);

        $intentOk = !isset($expect['intent']) || $result['intent'] === (string) $expect['intent'];
        $intentTotal++;
        if ($intentOk) {
            $intentCorrect++;
        }

        $replyTotal++;
        if ($evaluation['passed']) {
            $replyCorrect++;
        } else {
            $scenarioPassed = false;
            $failures[] = [
                'scenario_id' => $scenarioId,
                'scenario_name' => $scenarioName,
                'turn_index' => $turnIndex + 1,
                'user' => $userMessage,
                'intent' => $result['intent'],
                'source' => $result['source'],
                'confidence' => $result['confidence'],
                'reply_excerpt' => mb_substr($result['reply'], 0, 160, 'UTF-8'),
                'errors' => $evaluation['errors'],
            ];
        }

        $scenarioTurnRows[] = [
            'turn' => $turnIndex + 1,
            'user' => $userMessage,
            'intent' => $result['intent'],
            'source' => $result['source'],
            'confidence' => $result['confidence'],
            'passed' => $evaluation['passed'],
            'errors' => $evaluation['errors'],
            'reply_excerpt' => mb_substr($result['reply'], 0, 120, 'UTF-8'),
        ];
        $turnResults[] = $scenarioTurnRows[count($scenarioTurnRows) - 1];
    }

    $scenarioResults[] = [
        'id' => $scenarioId,
        'name' => $scenarioName,
        'lang' => $lang,
        'turn_count' => count($scenarioTurnRows),
        'passed' => $scenarioPassed,
        'turns' => $scenarioTurnRows,
    ];
}

$scenarioCount = count($scenarioResults);
$scenarioPassedCount = count(array_filter($scenarioResults, static fn(array $s): bool => !empty($s['passed'])));

$report = [
    'dataset' => $datasetPath,
    'evaluated_at' => date('c'),
    'pipeline' => 'rule-based multi-turn simulate_chatbot_turn() — no OpenAI',
    'scenario_count' => $scenarioCount,
    'turn_count' => $replyTotal,
    'scenario_success_rate' => $scenarioCount > 0 ? round($scenarioPassedCount / $scenarioCount, 4) : 0.0,
    'scenarios_passed' => $scenarioPassedCount,
    'scenarios_failed' => $scenarioCount - $scenarioPassedCount,
    'turn_intent_accuracy' => $intentTotal > 0 ? round($intentCorrect / $intentTotal, 4) : 0.0,
    'turn_reply_accuracy' => $replyTotal > 0 ? round($replyCorrect / $replyTotal, 4) : 0.0,
    'intent_correct' => $intentCorrect,
    'intent_total' => $intentTotal,
    'reply_correct' => $replyCorrect,
    'reply_total' => $replyTotal,
    'scenarios' => $scenarioResults,
    'failures' => $failures,
];

if (isset($opts['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit($failures === [] ? 0 : 1);
}

echo "ZERA Dialogue Evaluation — multi-turn end-to-end\n";
echo str_repeat('=', 60) . "\n";
echo "Scenarios:            {$scenarioCount}\n";
echo "Turns evaluated:      {$replyTotal}\n";
echo "Scenario success:     " . sprintf('%.2f%%', $report['scenario_success_rate'] * 100)
    . " ({$scenarioPassedCount}/{$scenarioCount})\n";
echo "Turn intent accuracy: " . sprintf('%.2f%%', $report['turn_intent_accuracy'] * 100)
    . " ({$intentCorrect}/{$intentTotal})\n";
echo "Turn reply accuracy:  " . sprintf('%.2f%%', $report['turn_reply_accuracy'] * 100)
    . " ({$replyCorrect}/{$replyTotal})\n\n";

echo "Per-scenario:\n";
echo str_pad('ID', 8) . str_pad('Pass', 6) . str_pad('Turns', 7) . "Name\n";
echo str_repeat('-', 60) . "\n";
foreach ($scenarioResults as $s) {
    echo str_pad((string) $s['id'], 8)
        . str_pad(!empty($s['passed']) ? 'OK' : 'FAIL', 6)
        . str_pad((string) $s['turn_count'], 7)
        . $s['name'] . "\n";
}

if (isset($opts['failures']) && $failures !== []) {
    echo "\nFailures:\n";
    foreach ($failures as $f) {
        echo "  [{$f['scenario_id']} turn {$f['turn_index']}] {$f['user']}\n";
        echo "    intent={$f['intent']} source={$f['source']} confidence={$f['confidence']}\n";
        foreach ($f['errors'] as $err) {
            echo "    - {$err}\n";
        }
        echo "    reply: {$f['reply_excerpt']}\n";
    }
} elseif ($failures !== []) {
    echo "\n" . count($failures) . " failing turn(s). Run with --failures to list them.\n";
}

if (isset($opts['save'])) {
    $outPath = $root . '/docs/eval_dialogue_results.json';
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nSaved report: {$outPath}\n";
}

exit($failures === [] ? 0 : 1);

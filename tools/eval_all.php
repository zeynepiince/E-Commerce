#!/usr/bin/env php
<?php
/**
 * Run the full ZERA evaluation suite (chatbot + platform).
 *
 * Suites (in order): intents, tr_search, dialogue, answer_quality, platform.
 *
 * Usage:
 *   php tools/eval_all.php
 *   php tools/eval_all.php --failures
 *   php tools/eval_all.php --json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$php = PHP_BINARY;
$opts = getopt('', ['failures', 'json', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php tools/eval_all.php [--failures] [--json]\n";
    exit(0);
}

$suites = [
    ['name' => 'intents', 'script' => 'eval_intents.php', 'args' => []],
    ['name' => 'tr_search', 'script' => 'eval_tr_search.php', 'args' => isset($opts['failures']) ? ['--failures'] : []],
    ['name' => 'dialogue', 'script' => 'eval_dialogue.php', 'args' => isset($opts['failures']) ? ['--failures'] : []],
    ['name' => 'answer_quality', 'script' => 'eval_answer_quality.php', 'args' => isset($opts['failures']) ? ['--failures'] : []],
    ['name' => 'platform', 'script' => 'eval_platform.php', 'args' => isset($opts['failures']) ? ['--failures'] : []],
];

$results = [];
$failed = false;

foreach ($suites as $suite) {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($root . '/tools/' . $suite['script']);
    foreach ($suite['args'] as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    $output = [];
    exec($cmd . ' 2>&1', $output, $code);
    $text = implode("\n", $output);
    $results[$suite['name']] = [
        'exit_code' => $code,
        'output' => $text,
        'passed' => $code === 0,
    ];
    if ($code !== 0) {
        $failed = true;
    }
}

if (isset($opts['json'])) {
    echo json_encode(['passed' => !$failed, 'suites' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit($failed ? 1 : 0);
}

echo "ZERA Full Evaluation Suite\n";
echo str_repeat('=', 60) . "\n";

foreach ($results as $name => $row) {
    $status = $row['passed'] ? 'PASS' : 'FAIL';
    echo str_pad($name, 18) . $status . "\n";
    if (!$row['passed'] || isset($opts['failures'])) {
        $lines = explode("\n", trim($row['output']));
        foreach (array_slice($lines, 0, 8) as $line) {
            echo '  ' . $line . "\n";
        }
        if (count($lines) > 8) {
            echo '  ... (' . (count($lines) - 8) . ' more lines)' . "\n";
        }
    }
}

echo str_repeat('-', 60) . "\n";
echo $failed ? "Overall: FAIL\n" : "Overall: PASS\n";
exit($failed ? 1 : 0);

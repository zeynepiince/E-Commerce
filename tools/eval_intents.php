#!/usr/bin/env php
<?php
/**
 * Evaluate rule-based detect_intent() against docs/intent_test_set.json
 *
 * Usage:
 *   php tools/eval_intents.php
 *   php tools/eval_intents.php --failures
 *   php tools/eval_intents.php --save
 *   php tools/eval_intents.php --json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$datasetPath = $root . '/docs/intent_test_set.json';
$intentPath = $root . '/E-Commerce/chatbot/intent.php';

if (!is_readable($datasetPath)) {
    fwrite(STDERR, "Dataset not found: {$datasetPath}\n");
    exit(1);
}
if (!is_readable($intentPath)) {
    fwrite(STDERR, "Intent module not found: {$intentPath}\n");
    exit(1);
}

require_once $intentPath;

$opts = getopt('', ['failures', 'save', 'json', 'help']);

if (isset($opts['help'])) {
    echo "Usage: php tools/eval_intents.php [--failures] [--save] [--json]\n";
    exit(0);
}

$raw = file_get_contents($datasetPath);
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['samples']) || !is_array($data['samples'])) {
    fwrite(STDERR, "Invalid dataset format in intent_test_set.json\n");
    exit(1);
}

$samples = $data['samples'];
$labels = $data['intents'] ?? [];
if ($labels === []) {
    foreach ($samples as $s) {
        $labels[] = (string) ($s['intent'] ?? 'general');
    }
    $labels = array_values(array_unique($labels));
}
sort($labels);

$confusion = [];
foreach ($labels as $a) {
    $confusion[$a] = [];
    foreach ($labels as $p) {
        $confusion[$a][$p] = 0;
    }
}

$correct = 0;
$failures = [];
$byLang = ['en' => ['total' => 0, 'correct' => 0], 'tr' => ['total' => 0, 'correct' => 0]];

foreach ($samples as $sample) {
    $text = (string) ($sample['text'] ?? '');
    $expected = (string) ($sample['intent'] ?? 'general');
    $lang = (string) ($sample['lang'] ?? 'en');
    $id = $sample['id'] ?? null;

    if ($text === '' || !isset($confusion[$expected])) {
        continue;
    }

    $predicted = detect_intent($text);
    if (!isset($confusion[$expected][$predicted])) {
        foreach ($labels as $lbl) {
            $confusion[$expected][$lbl] = $confusion[$expected][$lbl] ?? 0;
        }
        $confusion[$expected][$predicted] = 0;
    }

    $confusion[$expected][$predicted]++;
    $ok = ($predicted === $expected);
    if ($ok) {
        $correct++;
    } else {
        $failures[] = [
            'id' => $id,
            'text' => $text,
            'expected' => $expected,
            'predicted' => $predicted,
            'lang' => $lang,
        ];
    }

    if (isset($byLang[$lang])) {
        $byLang[$lang]['total']++;
        if ($ok) {
            $byLang[$lang]['correct']++;
        }
    }
}

$total = count($samples);
$accuracy = $total > 0 ? $correct / $total : 0.0;

$perClass = [];
$f1Sum = 0.0;
$classCount = 0;

foreach ($labels as $label) {
    $tp = (int) ($confusion[$label][$label] ?? 0);
    $fp = 0;
    $fn = 0;
    foreach ($labels as $other) {
        if ($other !== $label) {
            $fp += (int) ($confusion[$other][$label] ?? 0);
            $fn += (int) ($confusion[$label][$other] ?? 0);
        }
    }
    $precision = ($tp + $fp) > 0 ? $tp / ($tp + $fp) : 0.0;
    $recall = ($tp + $fn) > 0 ? $tp / ($tp + $fn) : 0.0;
    $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

    $perClass[$label] = [
        'support' => $tp + $fn,
        'tp' => $tp,
        'fp' => $fp,
        'fn' => $fn,
        'precision' => round($precision, 4),
        'recall' => round($recall, 4),
        'f1' => round($f1, 4),
    ];
    $f1Sum += $f1;
    $classCount++;
}

$macroF1 = $classCount > 0 ? $f1Sum / $classCount : 0.0;

$report = [
    'dataset' => $datasetPath,
    'evaluated_at' => date('c'),
    'classifier' => 'detect_intent() rule-based',
    'total_samples' => $total,
    'correct' => $correct,
    'accuracy' => round($accuracy, 4),
    'macro_f1' => round($macroF1, 4),
    'per_class' => $perClass,
    'confusion_matrix' => $confusion,
    'by_language' => $byLang,
    'failures' => $failures,
];

if (isset($opts['json'])) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

echo "ZERA Intent Evaluation — detect_intent()\n";
echo str_repeat('=', 60) . "\n";
echo "Dataset:     {$total} labeled samples\n";
echo "Accuracy:    " . sprintf('%.2f%%', $accuracy * 100) . " ({$correct}/{$total})\n";
echo "Macro F1:    " . sprintf('%.4f', $macroF1) . "\n\n";

echo "Per-class metrics:\n";
echo str_pad('Intent', 16) . str_pad('Prec', 8) . str_pad('Recall', 8) . str_pad('F1', 8) . "Support\n";
echo str_repeat('-', 52) . "\n";
foreach ($perClass as $intent => $m) {
    echo str_pad($intent, 16)
        . str_pad(sprintf('%.2f', $m['precision']), 8)
        . str_pad(sprintf('%.2f', $m['recall']), 8)
        . str_pad(sprintf('%.2f', $m['f1']), 8)
        . $m['support'] . "\n";
}

echo "\nBy language:\n";
foreach ($byLang as $lang => $stats) {
    if ($stats['total'] === 0) {
        continue;
    }
    $acc = $stats['correct'] / $stats['total'];
    echo "  {$lang}: " . sprintf('%.2f%%', $acc * 100) . " ({$stats['correct']}/{$stats['total']})\n";
}

echo "\nConfusion matrix (rows=actual, cols=predicted):\n";
$header = str_pad('', 14);
foreach ($labels as $lbl) {
    $header .= str_pad(substr($lbl, 0, 6), 8);
}
echo $header . "\n";
foreach ($labels as $actual) {
    $row = str_pad(substr($actual, 0, 12), 14);
    foreach ($labels as $pred) {
        $row .= str_pad((string) ($confusion[$actual][$pred] ?? 0), 8);
    }
    echo $row . "\n";
}

if (isset($opts['failures']) && $failures !== []) {
    echo "\nMisclassifications:\n";
    foreach ($failures as $f) {
        $id = $f['id'] ?? '?';
        echo "  [#{$id}] expected={$f['expected']} predicted={$f['predicted']} ({$f['lang']}) \"{$f['text']}\"\n";
    }
} elseif ($failures !== []) {
    echo "\n" . count($failures) . " misclassification(s). Run with --failures to list them.\n";
}

if (isset($opts['save'])) {
    $outPath = $root . '/docs/eval_intents_results.json';
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "\nSaved report: {$outPath}\n";
}

exit($failures === [] ? 0 : 1);

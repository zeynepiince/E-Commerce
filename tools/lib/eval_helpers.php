<?php

declare(strict_types=1);

/**
 * Shared helpers for tools/eval_*.php runners.
 */

/** @return array{passed:int, failed:array<int, array{suite:string, id:string, detail:string}>, skipped:array<int, array{suite:string, id:string, detail:string}>} */
function eval_report_new(): array
{
    return ['passed' => 0, 'failed' => [], 'skipped' => []];
}

function eval_pass(array &$report, string $suite, string $id): void
{
    $report['passed']++;
}

function eval_fail(array &$report, string $suite, string $id, string $detail): void
{
    $report['failed'][] = ['suite' => $suite, 'id' => $id, 'detail' => $detail];
}

function eval_skip(array &$report, string $suite, string $id, string $detail): void
{
    $report['skipped'][] = ['suite' => $suite, 'id' => $id, 'detail' => $detail];
}

function eval_assert(array &$report, bool $ok, string $suite, string $id, string $detail): void
{
    if ($ok) {
        eval_pass($report, $suite, $id);
        return;
    }
    eval_fail($report, $suite, $id, $detail);
}

/** @return array<string, ?string> */
function eval_backup_env(array $keys): array
{
    $backup = [];
    foreach ($keys as $key) {
        $val = getenv($key);
        $backup[$key] = $val === false ? null : (string) $val;
    }
    return $backup;
}

/** @param array<string, ?string> $backup */
function eval_restore_env(array $backup): void
{
    foreach ($backup as $key => $val) {
        if ($val === null) {
            putenv($key);
            unset($_ENV[$key]);
            continue;
        }
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
    }
}

function eval_set_env(string $key, string $value): void
{
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
}

function eval_unset_env(string $key): void
{
    putenv($key);
    unset($_ENV[$key]);
}

/** @param array{passed:int, failed:array, skipped:array} $report */
function eval_print_report(string $title, array $report, bool $showFailures = false): void
{
    echo $title . "\n";
    echo str_repeat('-', 60) . "\n";
    echo 'Passed:  ' . $report['passed'] . "\n";
    echo 'Failed:  ' . count($report['failed']) . "\n";
    echo 'Skipped: ' . count($report['skipped']) . "\n";

    if ($showFailures || count($report['failed']) > 0) {
        foreach ($report['failed'] as $row) {
            echo '  FAIL [' . $row['suite'] . '] ' . $row['id'] . ': ' . $row['detail'] . "\n";
        }
    }
    if ($showFailures && count($report['skipped']) > 0) {
        foreach ($report['skipped'] as $row) {
            echo '  SKIP [' . $row['suite'] . '] ' . $row['id'] . ': ' . $row['detail'] . "\n";
        }
    }
    echo str_repeat('-', 60) . "\n";
}

/** @param array{passed:int, failed:array, skipped:array} $report */
function eval_exit_code(array $report): int
{
    return count($report['failed']) > 0 ? 1 : 0;
}

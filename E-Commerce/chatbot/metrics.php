<?php

function ensure_metrics_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_metrics (
            metric_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            intent VARCHAR(64) NOT NULL,
            reference_intent VARCHAR(64) NULL,
            predicted_intent VARCHAR(64) NULL,
            response_source VARCHAR(32) NOT NULL,
            confidence_score DECIMAL(4,2) NOT NULL,
            latency_ms INT NOT NULL,
            used_ai TINYINT(1) NOT NULL DEFAULT 0,
            experiment_mode VARCHAR(16) NOT NULL,
            experiment_bucket VARCHAR(16) NOT NULL,
            escalated_to_human TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    // Backward compatibility for existing tables
    try { $pdo->exec("ALTER TABLE chatbot_metrics ADD COLUMN reference_intent VARCHAR(64) NULL AFTER intent"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE chatbot_metrics ADD COLUMN predicted_intent VARCHAR(64) NULL AFTER reference_intent"); } catch (Throwable $e) {}
    $ready = true;
}

function log_chatbot_metric(
    PDO $pdo,
    ?int $userId,
    string $intent,
    string $source,
    float $confidence,
    int $latencyMs,
    bool $usedAi,
    string $mode,
    string $bucket,
    bool $escalated,
    ?string $referenceIntent = null,
    ?string $predictedIntent = null
): void
{
    ensure_metrics_table($pdo);
    $stmt = $pdo->prepare("
      INSERT INTO chatbot_metrics (
        user_id, intent, reference_intent, predicted_intent, response_source,
        confidence_score, latency_ms, used_ai, experiment_mode, experiment_bucket, escalated_to_human
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $intent,
        $referenceIntent,
        $predictedIntent,
        $source,
        round($confidence, 2),
        $latencyMs,
        $usedAi ? 1 : 0,
        $mode,
        $bucket,
        $escalated ? 1 : 0
    ]);
}

function ensure_feedback_table(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_feedback (
            feedback_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            intent VARCHAR(64) NOT NULL,
            response_source VARCHAR(32) NOT NULL,
            confidence_score DECIMAL(4,2) NOT NULL DEFAULT 0.00,
            escalated_to_human TINYINT(1) NOT NULL DEFAULT 0,
            used_ai TINYINT(1) NOT NULL DEFAULT 0,
            is_helpful TINYINT(1) NOT NULL,
            user_message TEXT NULL,
            bot_reply TEXT NULL,
            page_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $ready = true;
}

function log_chatbot_feedback(
    PDO $pdo,
    ?int $userId,
    string $intent,
    string $source,
    float $confidence,
    bool $escalated,
    bool $usedAi,
    bool $isHelpful,
    string $userMessage,
    string $botReply,
    string $pagePath
): void {
    ensure_feedback_table($pdo);
    $stmt = $pdo->prepare("
      INSERT INTO chatbot_feedback (
        user_id, intent, response_source, confidence_score, escalated_to_human,
        used_ai, is_helpful, user_message, bot_reply, page_path
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $intent,
        $source,
        round($confidence, 2),
        $escalated ? 1 : 0,
        $usedAi ? 1 : 0,
        $isHelpful ? 1 : 0,
        $userMessage,
        $botReply,
        $pagePath
    ]);
}

function get_chatbot_learning_summary(PDO $pdo, int $days = 7): array
{
    ensure_metrics_table($pdo);
    ensure_feedback_table($pdo);
    $days = max(1, min(90, $days));

    $daysSql = (int) $days;

    $sourceStmt = $pdo->query("
      SELECT response_source,
             COUNT(*) AS total,
             AVG(is_helpful) AS helpful_rate
      FROM chatbot_feedback
      WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
      GROUP BY response_source
      ORDER BY helpful_rate DESC, total DESC
    ");
    $sourceRows = $sourceStmt ? ($sourceStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $totalsWindowStmt = $pdo->query("
      SELECT
        (SELECT COUNT(*) FROM chatbot_metrics WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)) AS metrics_count,
        (SELECT COUNT(*) FROM chatbot_feedback WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)) AS feedback_count
    ");
    $totalsWindow = $totalsWindowStmt ? ($totalsWindowStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

    $totalsAllStmt = $pdo->query("
      SELECT
        (SELECT COUNT(*) FROM chatbot_metrics) AS metrics_count,
        (SELECT COUNT(*) FROM chatbot_feedback) AS feedback_count
    ");
    $totalsAll = $totalsAllStmt ? ($totalsAllStmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

    $lowConfidenceStmt = $pdo->query("
      SELECT intent,
             COUNT(*) AS total,
             AVG(confidence_score) AS avg_confidence
      FROM chatbot_metrics
      WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
      GROUP BY intent
      HAVING AVG(confidence_score) < 0.60
      ORDER BY avg_confidence ASC, total DESC
      LIMIT 5
    ");
    $lowConfidenceRows = $lowConfidenceStmt ? ($lowConfidenceStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $escalationStmt = $pdo->query("
      SELECT intent,
             COUNT(*) AS total,
             SUM(CASE WHEN escalated_to_human = 1 THEN 1 ELSE 0 END) AS escalations,
             AVG(CASE WHEN escalated_to_human = 1 THEN 1 ELSE 0 END) AS escalation_rate
      FROM chatbot_metrics
      WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
      GROUP BY intent
      HAVING SUM(CASE WHEN escalated_to_human = 1 THEN 1 ELSE 0 END) > 0
      ORDER BY escalation_rate DESC, escalations DESC
      LIMIT 5
    ");
    $escalationRows = $escalationStmt ? ($escalationStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $confusionRows = [];
    try {
        $confusionStmt = $pdo->query("
      SELECT
        COALESCE(NULLIF(TRIM(reference_intent), ''), intent) AS true_intent,
        COALESCE(NULLIF(TRIM(predicted_intent), ''), intent) AS predicted_intent,
        COUNT(*) AS total
      FROM chatbot_metrics
      WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
      GROUP BY true_intent, predicted_intent
      ORDER BY total DESC
      LIMIT 100
    ");
        $confusionRows = $confusionStmt ? ($confusionStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        $fallbackConfusion = $pdo->query("
          SELECT intent AS true_intent, intent AS predicted_intent, COUNT(*) AS total
          FROM chatbot_metrics
          WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
          GROUP BY intent
          ORDER BY total DESC
          LIMIT 100
        ");
        $confusionRows = $fallbackConfusion ? ($fallbackConfusion->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    $experimentStmt = $pdo->query("
      SELECT experiment_bucket, confidence_score, latency_ms, escalated_to_human
      FROM chatbot_metrics
      WHERE created_at >= (NOW() - INTERVAL {$daysSql} DAY)
    ");
    $experimentRows = $experimentStmt ? ($experimentStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $experimentSummary = [];
    foreach ($experimentRows as $row) {
        $bucket = (string) ($row["experiment_bucket"] ?? "");
        if (!preg_match('/exp1:([AB])\|exp2:([AB])/i', $bucket, $m)) continue;
        $exp1 = strtoupper($m[1]);
        $exp2 = strtoupper($m[2]);
        $key = "exp1:" . $exp1 . "|exp2:" . $exp2;
        if (!isset($experimentSummary[$key])) {
            $experimentSummary[$key] = [
                "variant" => $key,
                "total" => 0,
                "success_count" => 0,
                "escalations" => 0,
                "latency_sum" => 0.0,
            ];
        }
        $confidence = (float) ($row["confidence_score"] ?? 0);
        $latency = (float) ($row["latency_ms"] ?? 0);
        $escalated = (int) ($row["escalated_to_human"] ?? 0) === 1;
        $experimentSummary[$key]["total"]++;
        $experimentSummary[$key]["latency_sum"] += $latency;
        if ($escalated) {
            $experimentSummary[$key]["escalations"]++;
        }
        // Proxy success: high confidence and no escalation
        if (!$escalated && $confidence >= 0.65) {
            $experimentSummary[$key]["success_count"]++;
        }
    }
    $experimentSummaryRows = [];
    foreach ($experimentSummary as $entry) {
        $total = max(1, (int) $entry["total"]);
        $experimentSummaryRows[] = [
            "variant" => $entry["variant"],
            "total" => (int) $entry["total"],
            "success_rate" => round(((int) $entry["success_count"]) / $total, 3),
            "escalation_rate" => round(((int) $entry["escalations"]) / $total, 3),
            "avg_response_time_ms" => round(((float) $entry["latency_sum"]) / $total, 2),
        ];
    }
    usort($experimentSummaryRows, static function (array $a, array $b): int {
        return ($b["total"] <=> $a["total"]);
    });

    $minimumSamplePerVariant = 30;
    $minimumSampleWarning = [];
    $bestVariant = null;
    if (!empty($experimentSummaryRows)) {
        foreach ($experimentSummaryRows as $row) {
            if ((int) ($row["total"] ?? 0) < $minimumSamplePerVariant) {
                $minimumSampleWarning[] = [
                    "variant" => (string) ($row["variant"] ?? "unknown"),
                    "total" => (int) ($row["total"] ?? 0),
                    "required_min" => $minimumSamplePerVariant,
                    "warning" => "Sample size is too small for reliable comparison.",
                ];
            }
        }
        $eligible = array_values(array_filter($experimentSummaryRows, static function (array $row) use ($minimumSamplePerVariant): bool {
            return (int) ($row["total"] ?? 0) >= $minimumSamplePerVariant;
        }));
        if (!empty($eligible)) {
            usort($eligible, static function (array $a, array $b): int {
                $scoreA = ((float) ($a["success_rate"] ?? 0)) - ((float) ($a["escalation_rate"] ?? 0)) * 0.5 - ((float) ($a["avg_response_time_ms"] ?? 0) / 10000.0);
                $scoreB = ((float) ($b["success_rate"] ?? 0)) - ((float) ($b["escalation_rate"] ?? 0)) * 0.5 - ((float) ($b["avg_response_time_ms"] ?? 0) / 10000.0);
                if ($scoreA === $scoreB) return ((int) ($b["total"] ?? 0)) <=> ((int) ($a["total"] ?? 0));
                return $scoreB <=> $scoreA;
            });
            $top = $eligible[0];
            $bestVariant = [
                "variant" => (string) ($top["variant"] ?? "unknown"),
                "total" => (int) ($top["total"] ?? 0),
                "success_rate" => (float) ($top["success_rate"] ?? 0),
                "escalation_rate" => (float) ($top["escalation_rate"] ?? 0),
                "avg_response_time_ms" => (float) ($top["avg_response_time_ms"] ?? 0),
                "selection_rule" => "max(success_rate - 0.5*escalation_rate) with latency tie-break",
            ];
        }
    }

    return [
        "window_days" => $days,
        "totals" => [
            "window" => [
                "metrics_count" => (int) ($totalsWindow["metrics_count"] ?? 0),
                "feedback_count" => (int) ($totalsWindow["feedback_count"] ?? 0),
            ],
            "all_time" => [
                "metrics_count" => (int) ($totalsAll["metrics_count"] ?? 0),
                "feedback_count" => (int) ($totalsAll["feedback_count"] ?? 0),
            ],
        ],
        "source_performance" => array_map(static function (array $row): array {
            return [
                "response_source" => (string) ($row["response_source"] ?? "unknown"),
                "total" => (int) ($row["total"] ?? 0),
                "helpful_rate" => round((float) ($row["helpful_rate"] ?? 0), 3),
            ];
        }, $sourceRows),
        "low_confidence_intents" => array_map(static function (array $row): array {
            return [
                "intent" => (string) ($row["intent"] ?? "unknown"),
                "total" => (int) ($row["total"] ?? 0),
                "avg_confidence" => round((float) ($row["avg_confidence"] ?? 0), 3),
            ];
        }, $lowConfidenceRows),
        "high_escalation_intents" => array_map(static function (array $row): array {
            return [
                "intent" => (string) ($row["intent"] ?? "unknown"),
                "total" => (int) ($row["total"] ?? 0),
                "escalations" => (int) ($row["escalations"] ?? 0),
                "escalation_rate" => round((float) ($row["escalation_rate"] ?? 0), 3),
            ];
        }, $escalationRows),
        "intent_confusion_matrix" => array_map(static function (array $row): array {
            return [
                "true_intent" => (string) ($row["true_intent"] ?? "unknown"),
                "predicted_intent" => (string) ($row["predicted_intent"] ?? "unknown"),
                "total" => (int) ($row["total"] ?? 0),
            ];
        }, $confusionRows),
        "experiment_performance" => $experimentSummaryRows,
        "minimum_sample_warning" => $minimumSampleWarning,
        "best_variant" => $bestVariant,
    ];
}


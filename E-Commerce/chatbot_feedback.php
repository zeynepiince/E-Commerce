<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/chatbot/metrics.php";
header("Content-Type: application/json; charset=UTF-8");

$isGet = ($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET";
$data = $isGet ? $_GET : json_decode(file_get_contents("php://input"), true);
if (!is_array($data)) $data = [];

$action = (string) ($data["action"] ?? ($isGet ? "summary" : "submit"));

try {
    if ($action === "summary") {
        $days = (int) ($data["days"] ?? 7);
        $summary = get_chatbot_learning_summary($pdo, $days);
        echo json_encode(["success" => true, "summary" => $summary]);
        exit;
    }

    $helpfulRaw = $data["helpful"] ?? null;
    if (!is_bool($helpfulRaw) && $helpfulRaw !== 0 && $helpfulRaw !== 1 && $helpfulRaw !== "0" && $helpfulRaw !== "1") {
        echo json_encode(["success" => false, "error" => "helpful is required"]);
        exit;
    }
    $isHelpful = (bool) ((int) $helpfulRaw);

    $intent = trim((string) ($data["intent"] ?? "general"));
    $source = trim((string) ($data["source"] ?? "rule"));
    $confidence = (float) ($data["confidence"] ?? 0);
    $escalated = !empty($data["escalated_to_human"]);
    $usedAi = !empty($data["used_ai"]);
    $userMessage = trim((string) ($data["user_message"] ?? ""));
    $botReply = trim((string) ($data["bot_reply"] ?? ""));
    $pagePath = trim((string) ($data["page"] ?? ""));

    $userId = !empty($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
    if (!$userId) {
        $uStmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
        $firstUserId = $uStmt ? $uStmt->fetchColumn() : false;
        $userId = $firstUserId ? (int) $firstUserId : null;
    }

    log_chatbot_feedback(
        $pdo,
        $userId ?: null,
        $intent !== "" ? $intent : "general",
        $source !== "" ? $source : "rule",
        $confidence,
        $escalated,
        $usedAi,
        $isHelpful,
        $userMessage,
        $botReply,
        $pagePath
    );

    $summary = get_chatbot_learning_summary($pdo, 7);
    echo json_encode(["success" => true, "summary" => $summary]);
} catch (Throwable $e) {
    $err = $isGet ? "Summary query failed" : "Feedback save failed";
    echo json_encode(["success" => false, "error" => $err]);
}


<?php
session_start();
require_once "db.php";
require_once __DIR__ . "/chatbot/helpers.php";
require_once __DIR__ . "/chatbot/responses.php";
require_once __DIR__ . "/chatbot/actions.php";
require_once __DIR__ . "/chatbot/ai.php";
require_once __DIR__ . "/chatbot/intent.php";
require_once __DIR__ . "/i18n.php";
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);
$rawMessage = trim((string) ($data["message"] ?? ""));
$message = to_lower($rawMessage);
$cart = is_array($data["cart"] ?? null) ? $data["cart"] : [];
$page = (string) ($data["page"] ?? "");
if ($rawMessage === "") { echo json_encode(["reply" => "Please type a message first."]); exit; }

$requestStart = microtime(true);
$intent = detect_intent($rawMessage);
$policyLockedIntent = detect_policy_lock_intent($rawMessage);
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}
$lang = get_current_lang();
$detectedLang = detect_language($rawMessage);
if ($lang !== "en" && $lang !== "tr") {
    $lang = $detectedLang;
}
$policyKnowledge = load_policy_knowledge();
$entities = extract_entities($rawMessage);
$intent = ($intent === "general" && infer_product_search_intent($pdo, $rawMessage, $entities)) ? "product_search" : $intent;
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}
// Reference intent before AI resolver (used as proxy "true" label in confusion matrix)
$referenceIntent = $intent;

$memory = $_SESSION["chatbot_memory"] ?? [];
if (!is_array($memory)) $memory = [];
$history = $memory["history"] ?? [];
if (!is_array($history)) $history = [];
$userProfile = $_SESSION["user_profile"] ?? [
    "prefers_budget" => false,
    "category_interest" => null,
];
if (!is_array($userProfile)) {
    $userProfile = ["prefers_budget" => false, "category_interest" => null];
}

if ($intent === "product_search" && !empty($memory["entities"]) && is_array($memory["entities"])) {
    foreach (["max_price", "min_price", "category_like", "product_type", "color", "size", "brand", "sort_by"] as $k) {
        if (empty($entities[$k]) && !empty($memory["entities"][$k])) $entities[$k] = $memory["entities"][$k];
    }
    if (empty($entities["features"]) && !empty($memory["entities"]["features"]) && is_array($memory["entities"]["features"])) {
        $entities["features"] = $memory["entities"]["features"];
    }
    if ((empty($entities["budget"]["min"]) && !empty($entities["min_price"])) || (empty($entities["budget"]["max"]) && !empty($entities["max_price"]))) {
        $entities["budget"]["min"] = $entities["min_price"] ?? null;
        $entities["budget"]["max"] = $entities["max_price"] ?? null;
    }
}

// Light-weight session persona update (behavior learning)
if (is_numeric($entities["max_price"] ?? null) || preg_match('/\b(under|below|budget|cheap|affordable|alt[ıi]|ucuz)\b/ui', $rawMessage)) {
    $userProfile["prefers_budget"] = true;
}
if (!empty($entities["category_like"])) {
    $userProfile["category_interest"] = (string) $entities["category_like"];
} elseif (!empty($entities["product_type"])) {
    $userProfile["category_interest"] = (string) $entities["product_type"];
}

// Apply persona to next recommendations when user is vague
if (($intent === "product_search" || $intent === "general")) {
    if (empty($entities["category_like"]) && !empty($userProfile["category_interest"])) {
        $entities["category_like"] = (string) $userProfile["category_interest"];
    }
    if ($userProfile["prefers_budget"] === true && empty($entities["max_price"]) && is_numeric($memory["last_suggested_max_price"] ?? null)) {
        $entities["max_price"] = round((float) $memory["last_suggested_max_price"] * 0.9, 2);
        $entities["budget"]["max"] = $entities["max_price"];
        if (empty($entities["sort_by"])) {
            $entities["sort_by"] = "price_asc";
        }
    }
}

if ($intent === "general" && (($memory["last_intent"] ?? "") === "product_search")) {
    $hasRefinementEntity = !empty($entities["max_price"]) || !empty($entities["min_price"]) || !empty($entities["category_like"]) || !empty($entities["color"]) || !empty($entities["size"]) || !empty($entities["brand"]);
    $hasRefinementWords = preg_match('/\b(those|these|only|sadece|olan|olanları|onlari|onları|goster|göster|daha ucuz|cheaper|black|white|siyah|beyaz|kablosuz|bluetooth|wireless|ucuz)\b/ui', $rawMessage);
    if ($hasRefinementEntity || $hasRefinementWords) $intent = "product_search";
}

// Merge memory context for follow-up product refinements
if ($intent === "product_search" && !empty($memory["entities"]) && is_array($memory["entities"])) {
    foreach (["category_like", "color", "size", "brand"] as $k) {
        if (empty($entities[$k]) && !empty($memory["entities"][$k])) $entities[$k] = $memory["entities"][$k];
    }
    if (empty($entities["keywords"]) && !empty($memory["entities"]["keywords"]) && is_array($memory["entities"]["keywords"])) {
        $entities["keywords"] = $memory["entities"]["keywords"];
    }

    // "biraz daha ucuz" / "cheaper" => tighten max_price based on previous context
    $asksCheaper = preg_match('/\b(cheaper|more affordable|daha ucuz|biraz daha ucuz|ucuz)\b/ui', $rawMessage);
    if ($asksCheaper) {
        $prevMax = null;
        if (is_numeric($entities["max_price"] ?? null)) {
            $prevMax = (float) $entities["max_price"];
        } elseif (is_numeric($memory["last_suggested_max_price"] ?? null)) {
            $prevMax = (float) $memory["last_suggested_max_price"];
        } elseif (is_numeric($memory["entities"]["max_price"] ?? null)) {
            $prevMax = (float) $memory["entities"]["max_price"];
        }
        if (is_numeric($prevMax) && $prevMax > 0) {
            $entities["max_price"] = round($prevMax * 0.85, 2);
            $entities["budget"]["max"] = $entities["max_price"];
            $entities["sort_by"] = "price_asc";
        }
    }
}

$rate = $_SESSION["chatbot_rate"] ?? ["window_start" => time(), "count" => 0];
if (!is_array($rate) || !isset($rate["window_start"], $rate["count"])) $rate = ["window_start" => time(), "count" => 0];
if ((time() - (int) $rate["window_start"]) > 60) $rate = ["window_start" => time(), "count" => 0];
$rate["count"] = (int) $rate["count"] + 1;
$_SESSION["chatbot_rate"] = $rate;
if ($rate["count"] > 20) {
    echo json_encode([
        "reply" => $lang === "tr" ? "Çok hızlı mesaj gönderiyorsun. Lütfen 1 dakika bekleyip tekrar dene." : "You're sending messages too quickly. Please wait a minute and try again.",
        "intent" => "rate_limit",
        "entities" => [],
        "suggested_products" => [],
        "redirect_url" => null,
        "source" => "rate_limit",
    ]);
    exit;
}

$apiKey = getenv("OPENAI_API_KEY") ?: "";
$chatUserName = get_chat_user_name();
$intent = resolve_intent($intent, $apiKey, $rawMessage);
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}
$intentConfidence = ($policyLockedIntent !== null)
    ? 0.96
    : ((preg_match('/(\?|\b(how|what|where|when|why|nasıl|nasil|ne|nerede|ne zaman|neden)\b)/ui', $rawMessage) && $intent === "general") ? 0.45 : 0.72);
$memory["last_intent"] = $intent;
$memory["entities"] = $entities;
$memory["last_message"] = $rawMessage;
$memory["last_cart"] = array_slice($cart, 0, 20);
$_SESSION["chatbot_memory"] = $memory;
$_SESSION["user_profile"] = $userProfile;

$experimentMode = "default";
$experimentBucket = "default";
$experimentVariants = [
    "exp1_guardrail" => "A",
    "exp2_fallback" => "A"
];

$usedAi = false;
$guardrailRejected = false;
[$reply, $suggestedProducts, $redirectUrl, $responseSource] = handle_intent_action($pdo, $intent, $rawMessage, $lang, $policyKnowledge, $entities);

if (
    in_array($intent, ["shipping", "returns", "payment", "order_status"], true)
    && (
        $responseSource === "product_search"
        || !empty($suggestedProducts)
        || preg_match('/^\s*-\s.+\(\$\d+/m', $reply)
        || str_contains(to_lower($reply), "here are some good options")
    )
) {
    $intent = $policyLockedIntent ?: $intent;
    [$reply, $suggestedProducts, $redirectUrl, $responseSource] = handle_intent_action($pdo, $intent, $rawMessage, $lang, $policyKnowledge, $entities);
}

if (should_use_ai($apiKey) && $intent !== "product_search") {
    $contextProducts = !empty($suggestedProducts) ? $suggestedProducts : fetch_top_products($pdo, 6);
    $aiReply = call_openai_chat($apiKey, $rawMessage, $page, build_cart_context($cart), $contextProducts, $history, build_policy_context($policyKnowledge, $lang), $lang, $chatUserName);
    $isStrictGuardrail = (($experimentVariants["exp1_guardrail"] ?? "A") === "B");
    $passesGuardrail = false;
    if (is_string($aiReply) && $aiReply !== "") {
        $passesGuardrail = $isStrictGuardrail
            ? is_ai_reply_grounded_strict($aiReply, $intent, $contextProducts)
            : is_ai_reply_grounded($aiReply, $intent, $contextProducts);
    }
    if (is_string($aiReply) && $aiReply !== "" && $passesGuardrail) {
        $reply = $aiReply;
        $responseSource = "ai";
        $usedAi = true;
    } elseif (is_string($aiReply) && $aiReply !== "") {
        $guardrailRejected = true;
    }
}

if (!empty($suggestedProducts)) {
    $prices = array_map(static fn($p) => (float) ($p["price"] ?? 0), $suggestedProducts);
    $prices = array_filter($prices, static fn($v) => $v > 0);
    if (!empty($prices)) {
        $memory["last_suggested_max_price"] = max($prices);
    }
}

if ($chatUserName !== "") {
    $startsWithName = preg_match('/^\s*' . preg_quote($chatUserName, '/') . '\b/ui', $reply) === 1;
    if (!$startsWithName) {
        $reply = $chatUserName . ", " . $reply;
    }
}

$clarificationCount = (int) ($memory["clarification_count"] ?? 0);
$confidence = estimate_confidence(
    $intent,
    $responseSource,
    $suggestedProducts,
    $reply,
    $guardrailRejected,
    $clarificationCount
);
$confidence = min($confidence, $intentConfidence);
$lowConfidenceCount = (int) ($memory["low_confidence_count"] ?? 0);
$askedClarification = false;
$escalatedToHuman = false;
$didYouMean = [];

if ($confidence < 0.55) {
    $fallbackVariant = (string) ($experimentVariants["exp2_fallback"] ?? "A");
    if ($fallbackVariant === "B") {
        [$reply, $escalatedToHuman] = maybe_add_escalation($reply, 0.01, $lang);
        $memory["low_confidence_count"] = 0;
    } else {
        if ($lowConfidenceCount <= 0) {
            $clarify = build_clarification_question($intent, $lang);
            $reply = trim($reply) . "\n\n" . $clarify;
            $askedClarification = true;
            $memory["low_confidence_count"] = 1;
            $memory["clarification_count"] = $clarificationCount + 1;
        } else {
            [$reply, $escalatedToHuman] = maybe_add_escalation($reply, $confidence, $lang);
            $memory["low_confidence_count"] = 0;
        }
    }
} else {
    $memory["low_confidence_count"] = 0;
}

if ($confidence < 0.65) {
    if ($intent === "product_search") {
        $didYouMean = [
            $lang === "tr" ? "3000 TL altı öner" : "Suggest items under $100",
            $lang === "tr" ? "Kablosuz modelleri göster" : "Show wireless options",
            $lang === "tr" ? "En popüler ürünler" : "Show most popular products",
        ];
    } elseif ($intent === "shipping" || $intent === "order_status") {
        $didYouMean = [
            $lang === "tr" ? "Siparişim nerede?" : "Where is my order?",
            $lang === "tr" ? "Tahmini teslim tarihi" : "Estimated delivery date",
            $lang === "tr" ? "Kargo ücreti ne kadar?" : "How much is shipping?",
        ];
    } else {
        $didYouMean = [
            $lang === "tr" ? "Ürün öner" : "Suggest products",
            $lang === "tr" ? "İade koşulları neler?" : "What is your return policy?",
            $lang === "tr" ? "Ödeme yöntemleri neler?" : "What payment methods do you support?",
        ];
    }
}
$history[] = ["role" => "user", "content" => $rawMessage];
$history[] = ["role" => "assistant", "content" => $reply];
$memory["history"] = array_slice($history, -10);
$_SESSION["chatbot_memory"] = $memory;

$user_id = !empty($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;
if (!$user_id) {
    try {
        $uStmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
        $firstUserId = $uStmt ? $uStmt->fetchColumn() : false;
        $user_id = $firstUserId ? (int) $firstUserId : null;
    } catch (Throwable $e) {
        $user_id = null;
    }
}

if ($user_id) {
    try {
        $stmt = $pdo->prepare("INSERT INTO support_interactions (user_id, message, sender, intent) VALUES (?, ?, 'user', ?) ");
        $stmt->execute([ $user_id, $rawMessage, $intent ]);
        $stmt = $pdo->prepare("INSERT INTO support_interactions (user_id, message, sender, intent) VALUES (?, ?, 'bot', ?)");
        $stmt->execute([ $user_id, $reply, $intent ]);
    } catch (Throwable $e) {
        // Support interaction logging should never block metric logging.
    }
}

echo json_encode([
    "reply" => $reply,
    "intent" => $intent,
    "entities" => $entities,
    "suggested_products" => $suggestedProducts,
    "redirect_url" => $redirectUrl,
    "source" => $responseSource,
    "used_ai" => $usedAi,
    "confidence" => round($confidence, 2),
    "experiment_mode" => $experimentMode,
    "experiment_bucket" => $experimentBucket,
    "experiment_variants" => $experimentVariants,
    "asked_clarification" => $askedClarification,
    "escalated_to_human" => $escalatedToHuman,
    "did_you_mean" => $didYouMean,
    "user_profile" => $userProfile,
]);


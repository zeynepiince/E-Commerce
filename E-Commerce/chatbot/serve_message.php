<?php

/**
 * Chat message handler. Expects $data (request payload array) and active session/PDO.
 * Included from recommended_api.php (InfinityFree-safe) or support_chat.php (local).
 */

if (!isset($data) || !is_array($data)) {
    $data = [];
}

$rawMessage = trim((string) ($data['message'] ?? ''));
$quickAction = trim((string) ($data['quick_action'] ?? ''));
if ($rawMessage === '' && $quickAction !== '') {
    $langForAction = function_exists('get_current_lang') ? get_current_lang() : 'en';
    $rawMessage = resolve_quick_action_message($quickAction, $langForAction);
}
$message = to_lower($rawMessage);
$cart = is_array($data['cart'] ?? null) ? $data['cart'] : [];
$page = (string) ($data['page'] ?? '');

if ($rawMessage === '') {
    echo json_encode(['reply' => 'Please type a message first.']);
    exit;
}

$requestStart = microtime(true);
$intent = detect_intent($rawMessage);
$policyLockedIntent = detect_policy_lock_intent($rawMessage);
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}
$lang = get_current_lang();
$detectedLang = detect_language($rawMessage);
if ($lang !== 'en' && $lang !== 'tr') {
    $lang = $detectedLang;
}
$policyKnowledge = load_policy_knowledge();
$entities = extract_entities($rawMessage);

$memory = $_SESSION['chatbot_memory'] ?? [];
if (!is_array($memory)) {
    $memory = [];
}
$history = $memory['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}
$userProfile = $_SESSION['user_profile'] ?? [
    'prefers_budget' => false,
    'category_interest' => null,
];
if (!is_array($userProfile)) {
    $userProfile = ['prefers_budget' => false, 'category_interest' => null];
}

if ($quickAction !== '') {
    $entities = prepare_entities_for_product_quick_action($entities, $quickAction, $rawMessage);
} elseif (is_generic_product_recommendation_request($rawMessage, $quickAction)) {
    $entities = reset_entities_for_generic_recommendation($entities);
}

if (is_fresh_product_search_quick_action($quickAction) || !empty($entities['_fresh_product_search'])) {
    [$memory, $userProfile] = reset_chatbot_product_session_context($memory, $userProfile);
}

$favoriteIdsForChat = parse_favorite_product_ids($data['favorite_ids'] ?? []);
$memory['_favorite_ids'] = $favoriteIdsForChat;

$lastSuggested = $memory['last_suggested_products'] ?? [];
$hadProductContext = in_array(($memory['last_intent'] ?? ''), ['product_search', 'product_followup'], true)
    || (is_array($lastSuggested) && $lastSuggested !== []);

if ($intent === 'general' && $hadProductContext) {
    $isSizeFollowup = !empty($entities['size'])
        || preg_match('/\b(sizes?|beden|numara)\b/ui', $rawMessage);
    $isStockFollowup = preg_match('/\b(stock|stok|stokta|in\s*stock|out\s*of\s*stock)\b/ui', $rawMessage)
        || (preg_match('/\b(available|availability)\b/ui', $rawMessage) && !preg_match('/\b(sizes?|beden)\b/ui', $rawMessage));
    $hasRefinementEntity = !empty($entities['max_price']) || !empty($entities['min_price']) || !empty($entities['category_like']) || !empty($entities['color']) || !empty($entities['size']) || !empty($entities['brand']) || !empty($entities['audience']);
    $hasRefinementWords = preg_match('/\b(those|these|only|sadece|olan|olanları|onlari|onları|goster|göster|daha ucuz|cheaper|black|white|siyah|beyaz|kablosuz|bluetooth|wireless|ucuz)\b/ui', $rawMessage);
    $isAudienceCorrection = is_audience_correction_message($rawMessage);

    if ($isSizeFollowup || $isStockFollowup) {
        $intent = 'product_followup';
    } elseif ($hasRefinementEntity || $hasRefinementWords || $isAudienceCorrection) {
        $intent = 'product_search';
    }
}

$intent = ($intent === 'general' && infer_product_search_intent($pdo, $rawMessage, $entities)) ? 'product_search' : $intent;
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}

$sessionCtx = apply_product_search_session_context($entities, $rawMessage, $intent, $memory, $userProfile);
$entities = $sessionCtx['entities'];
$userProfile = $sessionCtx['userProfile'];

$rate = $_SESSION['chatbot_rate'] ?? ['window_start' => time(), 'count' => 0];
if (!is_array($rate) || !isset($rate['window_start'], $rate['count'])) {
    $rate = ['window_start' => time(), 'count' => 0];
}
if ((time() - (int) $rate['window_start']) > 60) {
    $rate = ['window_start' => time(), 'count' => 0];
}
$rate['count'] = (int) $rate['count'] + 1;
$_SESSION['chatbot_rate'] = $rate;
if ($rate['count'] > 20) {
    echo json_encode([
        'reply' => $lang === 'tr' ? 'Çok hızlı mesaj gönderiyorsun. Lütfen 1 dakika bekleyip tekrar dene.' : "You're sending messages too quickly. Please wait a minute and try again.",
        'intent' => 'rate_limit',
        'entities' => [],
        'suggested_products' => [],
        'redirect_url' => null,
        'source' => 'rate_limit',
    ]);
    exit;
}

$apiKey = getenv('OPENAI_API_KEY') ?: '';
$chatUserName = get_chat_user_name();
$intent = resolve_intent($intent, $apiKey, $rawMessage);
if ($policyLockedIntent !== null) {
    $intent = $policyLockedIntent;
}
$intentConfidence = ($policyLockedIntent !== null)
    ? 0.96
    : ($intent === 'product_followup'
        ? 0.92
        : ((preg_match('/(\?|\b(how|what|where|when|why|nasıl|nasil|ne|nerede|ne zaman|neden)\b)/ui', $rawMessage) && $intent === 'general') ? 0.45 : 0.72));
$memory['last_intent'] = $intent;
$memory['entities'] = $entities;
$memory['last_message'] = $rawMessage;
$memory['last_cart'] = array_slice($cart, 0, 20);
$_SESSION['chatbot_memory'] = $memory;
$_SESSION['user_profile'] = $userProfile;

$experimentMode = get_experiment_mode();
$experimentBucket = get_reported_experiment_bucket();
$experimentVariants = get_experiment_variants();

$usedAi = false;
$guardrailRejected = false;
$chatUserId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$entities['_raw_message'] = $rawMessage;
[$reply, $suggestedProducts, $redirectUrl, $responseSource, $actionEntities] = handle_intent_action($pdo, $intent, $rawMessage, $lang, $policyKnowledge, $entities, $chatUserId, $memory);
if (is_array($actionEntities ?? null) && $actionEntities !== []) {
    $entities = $actionEntities;
}

if (policy_intent_has_product_leak($intent, $reply, $suggestedProducts, $responseSource)) {
    $intent = $policyLockedIntent ?: $intent;
    $chatUserId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    [$reply, $suggestedProducts, $redirectUrl, $responseSource, $actionEntities] = handle_intent_action($pdo, $intent, $rawMessage, $lang, $policyKnowledge, $entities, $chatUserId, $memory);
    if (is_array($actionEntities ?? null) && $actionEntities !== []) {
        $entities = $actionEntities;
    }
}

[$reply, $suggestedProducts, $responseSource] = enforce_response_consistency(
    $pdo,
    $intent,
    $reply,
    $suggestedProducts,
    $entities,
    $lang,
    $responseSource
);

if (should_use_ai($apiKey) && !in_array($intent, ['product_search', 'product_followup'], true)) {
    $contextProducts = in_array($intent, ['shipping', 'returns', 'payment', 'order_status'], true)
        ? []
        : (!empty($suggestedProducts) ? $suggestedProducts : fetch_top_products($pdo, 6));
    $aiReply = call_openai_chat($apiKey, $rawMessage, $page, build_cart_context($cart), $contextProducts, $history, build_policy_context($policyKnowledge, $lang), $lang, $chatUserName);
    $isStrictGuardrail = (($experimentVariants['exp1_guardrail'] ?? 'A') === 'B');
    $passesGuardrail = false;
    if (is_string($aiReply) && $aiReply !== '') {
        $passesGuardrail = $isStrictGuardrail
            ? is_ai_reply_grounded_strict($aiReply, $intent, $contextProducts)
            : is_ai_reply_grounded($aiReply, $intent, $contextProducts);
    }
    if (is_string($aiReply) && $aiReply !== '' && $passesGuardrail) {
        $reply = $aiReply;
        $responseSource = 'ai';
        $usedAi = true;
    } elseif (is_string($aiReply) && $aiReply !== '') {
        $guardrailRejected = true;
    }
}

if (!empty($suggestedProducts)) {
    $memory['last_suggested_products'] = array_slice($suggestedProducts, 0, 6);
    $prices = array_map(static fn($p) => (float) ($p['price'] ?? 0), $suggestedProducts);
    $prices = array_filter($prices, static fn($v) => $v > 0);
    if (!empty($prices)) {
        $memory['last_suggested_max_price'] = max($prices);
    }
}

if ($chatUserName !== '') {
    $startsWithName = preg_match('/^\s*' . preg_quote($chatUserName, '/') . '\b/ui', $reply) === 1;
    $isFailureReply = preg_match('/\b(bulamadım|bulunamadı|couldn\'t find|no match|tam eşleşme)\b/ui', $reply) === 1;
    if (!$startsWithName && !$isFailureReply) {
        $reply = $chatUserName . ', ' . $reply;
    }
}

$clarificationCount = (int) ($memory['clarification_count'] ?? 0);
$confidence = estimate_confidence(
    $intent,
    $responseSource,
    $suggestedProducts,
    $reply,
    $guardrailRejected,
    $clarificationCount
);
$confidence = min($confidence, $intentConfidence);
$lowConfidenceCount = (int) ($memory['low_confidence_count'] ?? 0);
$askedClarification = false;
$escalatedToHuman = false;
$didYouMean = [];

$lowConfidenceThreshold = confidence_low_threshold();
$didYouMeanThreshold = confidence_did_you_mean_threshold();

if ($confidence < $lowConfidenceThreshold && !should_skip_clarification($intent, $entities, $reply)) {
    $fallbackVariant = (string) ($experimentVariants['exp2_fallback'] ?? 'A');
    if ($fallbackVariant === 'B') {
        [$reply, $escalatedToHuman] = maybe_add_escalation($reply, 0.01, $lang);
        $memory['low_confidence_count'] = 0;
    } else {
        if ($lowConfidenceCount <= 0) {
            $clarify = build_clarification_question($intent, $lang);
            $reply = trim($reply) . "\n\n" . $clarify;
            $askedClarification = true;
            $memory['low_confidence_count'] = 1;
            $memory['clarification_count'] = $clarificationCount + 1;
        } else {
            [$reply, $escalatedToHuman] = maybe_add_escalation($reply, $confidence, $lang);
            $memory['low_confidence_count'] = 0;
        }
    }
} else {
    $memory['low_confidence_count'] = 0;
}

if ($confidence < $didYouMeanThreshold) {
    $didYouMean = build_did_you_mean_suggestions($intent, $lang);
}
$history[] = ['role' => 'user', 'content' => $rawMessage];
$history[] = ['role' => 'assistant', 'content' => $reply];
$memory['history'] = array_slice($history, -10);
$_SESSION['chatbot_memory'] = $memory;

$recommendedProductId = null;
if (!empty($suggestedProducts[0]['product_id'])) {
    $recommendedProductId = (int) $suggestedProducts[0]['product_id'];
}

if ($chatUserId) {
    try {
        $stmt = $pdo->prepare("INSERT INTO support_interactions (user_id, message, sender, intent) VALUES (?, ?, 'user', ?) ");
        $stmt->execute([$chatUserId, $rawMessage, $intent]);
        $stmt = $pdo->prepare("INSERT INTO support_interactions (user_id, message, sender, intent, recommended_product_id) VALUES (?, ?, 'bot', ?, ?)");
        $stmt->execute([$chatUserId, $reply, $intent, $recommendedProductId > 0 ? $recommendedProductId : null]);
    } catch (Throwable $e) {
        // Non-blocking log failure.
    }
}

$memory['last_confidence'] = round($confidence, 2);
$memory['last_experiment'] = [
    'mode' => $experimentMode,
    'bucket' => $experimentBucket,
    'variants' => $experimentVariants,
];
$_SESSION['chatbot_memory'] = $memory;

echo json_encode([
    'reply' => $reply,
    'intent' => $intent,
    'entities' => $entities,
    'suggested_products' => $suggestedProducts,
    'redirect_url' => $redirectUrl,
    'source' => $responseSource,
    'used_ai' => $usedAi,
    'confidence' => round($confidence, 2),
    'confidence_thresholds' => [
        'low' => $lowConfidenceThreshold,
        'did_you_mean' => $didYouMeanThreshold,
    ],
    'experiment_mode' => $experimentMode,
    'experiment_bucket' => $experimentBucket,
    'experiment_variants' => $experimentVariants,
    'latency_ms' => (int) round((microtime(true) - $requestStart) * 1000),
    'asked_clarification' => $askedClarification,
    'escalated_to_human' => $escalatedToHuman,
    'did_you_mean' => $didYouMean,
    'user_profile' => $userProfile,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

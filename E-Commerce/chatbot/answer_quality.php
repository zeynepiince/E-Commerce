<?php

function aq_significant_tokens(string $text): array
{
    $lower = function_exists('to_lower') ? to_lower($text) : strtolower($text);
    $parts = preg_split('/[^\p{L}\p{N}]+/u', $lower) ?: [];
    $stop = [
        'the', 'and', 'for', 'you', 'your', 'from', 'with', 'that', 'this', 'can', 'are', 'was', 'have',
        'bir', 'için', 'icin', 'veya', 'olan', 'gibi', 'daha', 'sonra', 'üzerinden', 'uzerinden',
    ];
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || mb_strlen($p) < 4) {
            continue;
        }
        if (in_array($p, $stop, true)) {
            continue;
        }
        $out[$p] = true;
    }
    return array_keys($out);
}

/**
 * @return array{passed:bool, score:float, matched:array<int,string>, expected_count:int}
 */
function verify_policy_reply_grounded(string $reply, string $intent, string $lang, array $knowledge, float $minScore = 0.18): array
{
    $policy = get_policy_reply($knowledge, $intent, $lang);
    if (!is_string($policy) || trim($policy) === '') {
        return ['passed' => false, 'score' => 0.0, 'matched' => [], 'expected_count' => 0];
    }

    $tokens = aq_significant_tokens($policy);
    if ($tokens === []) {
        return ['passed' => true, 'score' => 1.0, 'matched' => [], 'expected_count' => 0];
    }

    $replyLower = function_exists('to_lower') ? to_lower($reply) : strtolower($reply);
    $matched = [];
    foreach ($tokens as $token) {
        if (str_contains($replyLower, $token)) {
            $matched[] = $token;
        }
    }

    $score = count($matched) / count($tokens);
    return [
        'passed' => $score >= $minScore || count($matched) >= 3,
        'score' => round($score, 4),
        'matched' => $matched,
        'expected_count' => count($tokens),
    ];
}

/**
 * @return array{passed:bool, errors:array<int,string>, db_qty:int|null, reply_qty:int|null}
 */
function verify_stock_reply_matches_db(string $reply, array $product): array
{
    $dbQty = (int) ($product['stock_quantity'] ?? 0);
    $errors = [];
    $replyQty = null;

    if (preg_match('/\b(\d+)\s*(units?\s+available|adet)\b/ui', $reply, $m)) {
        $replyQty = (int) $m[1];
        if ($replyQty !== $dbQty) {
            $errors[] = "stock quantity in reply ({$replyQty}) != DB ({$dbQty})";
        }
    } elseif (preg_match('/\((\d+)\s+adet\)/u', $reply, $m)) {
        $replyQty = (int) $m[1];
        if ($replyQty !== $dbQty) {
            $errors[] = "stock quantity in reply ({$replyQty}) != DB ({$dbQty})";
        }
    }

    $replyLower = function_exists('to_lower') ? to_lower($reply) : strtolower($reply);
    if ($dbQty > 0) {
        if (!preg_match('/\b(in stock|stokta)\b/ui', $replyLower) && $replyQty === null) {
            $errors[] = 'in-stock product should mention in stock/stokta or numeric quantity';
        }
        if (preg_match('/\b(out of stock|stokta yok)\b/ui', $replyLower)) {
            $errors[] = 'DB has stock but reply says out of stock';
        }
    } else {
        if (!preg_match('/\b(out of stock|stokta yok)\b/ui', $replyLower)) {
            $errors[] = 'zero DB stock should mention out of stock/stokta yok';
        }
    }

    return [
        'passed' => $errors === [],
        'errors' => $errors,
        'db_qty' => $dbQty,
        'reply_qty' => $replyQty,
    ];
}

/**
 * @return array{passed:bool, errors:array<int,string>, expected_sizes:array<int,string>}
 */
function verify_size_reply_matches_product(string $reply, array $product, ?string $requestedSize = null): array
{
    if (!function_exists('get_product_sizes')) {
        require_once __DIR__ . '/../functions.php';
    }

    $expected = array_map(
        static fn($s) => strtoupper((string) $s),
        function_exists('resolve_chat_product_sizes')
            ? resolve_chat_product_sizes($product)
            : get_product_sizes($product)
    );
    $errors = [];

    if ($requestedSize !== null && $requestedSize !== '') {
        $req = strtoupper($requestedSize);
        $claimsAvailable = (bool) preg_match('/\b(yes|evet|available|mevcut|var)\b/ui', $reply);
        $claimsUnavailable = (bool) preg_match('/\b(not listed|yok|not available|listede yok)\b/ui', $reply);
        $inExpected = in_array($req, $expected, true);

        if ($inExpected && $claimsUnavailable) {
            $errors[] = "size {$req} exists in catalog but reply says unavailable";
        }
        if (!$inExpected && $claimsAvailable && preg_match('/\b' . preg_quote($req, '/') . '\b/i', $reply)) {
            $errors[] = "size {$req} not in expected list but reply claims available";
        }
    }

    if (preg_match('/\b(?:sizes?|bedenleri?)\s*:\s*([^\n\.]+)/ui', $reply, $m)) {
        $listed = preg_split('/\s*,\s*/', $m[1]) ?: [];
        foreach ($listed as $sizeRaw) {
            $size = strtoupper(trim((string) $sizeRaw));
            if ($size === '' || preg_match('/^\d+$/', $size)) {
                continue;
            }
            if ($expected !== [] && !in_array($size, $expected, true)) {
                $errors[] = "reply lists size {$size} not in expected sizes [" . implode(', ', $expected) . ']';
            }
        }
    }

    if ($expected === [] && preg_match('/\b(sizes?|beden)\s*:/ui', $reply)) {
        $errors[] = 'reply lists sizes but product has no inferable size catalog';
    }

    return [
        'passed' => $errors === [],
        'errors' => $errors,
        'expected_sizes' => $expected,
    ];
}

/**
 * @return array{passed:bool, errors:array<int,string>}
 */
function verify_product_prices_grounded(string $reply, array $products): array
{
    $allowed = [];
    foreach ($products as $p) {
        $price = (float) ($p['price'] ?? 0);
        if ($price > 0) {
            $allowed[] = number_format($price, 2, '.', '');
        }
    }

    $errors = [];
    if (preg_match_all('/\$\s*(\d+(?:\.\d{1,2})?)/u', $reply, $m)) {
        foreach ($m[1] as $priceStr) {
            $norm = number_format((float) $priceStr, 2, '.', '');
            if (!in_array($norm, $allowed, true)) {
                $errors[] = "reply price \${$norm} not in suggested product prices";
            }
        }
    }

    return ['passed' => $errors === [], 'errors' => $errors];
}

/**
 * @return array{passed:bool, accepted:bool, expected_accepted:bool, guardrail:string}
 */
function evaluate_guardrail_case(string $reply, string $intent, array $products, bool $expectAccepted, string $guardrail = 'normal'): array
{
    $strict = ($guardrail === 'strict');
    $accepted = $strict
        ? is_ai_reply_grounded_strict($reply, $intent, $products)
        : is_ai_reply_grounded($reply, $intent, $products);

    return [
        'passed' => $accepted === $expectAccepted,
        'accepted' => $accepted,
        'expected_accepted' => $expectAccepted,
        'guardrail' => $guardrail,
    ];
}

/**
 * @param array<string,mixed> $grounding
 * @param array<string,mixed> $result
 * @return array{passed:bool, errors:array<int,string>, checks:array<string,bool>}
 */
function evaluate_grounding_expectations(
    PDO $pdo,
    array $grounding,
    array $result,
    string $lang,
    array $policyKnowledge,
    array $memory
): array {
    $errors = [];
    $checks = [];
    $reply = (string) ($result['reply'] ?? '');
    $products = $result['suggested_products'] ?? [];
    if (!is_array($products)) {
        $products = [];
    }

    $contextProduct = $products[0] ?? ($memory['last_suggested_products'][0] ?? null);
    if (is_array($contextProduct) && !empty($contextProduct['product_id'])) {
        try {
            $stmt = $pdo->prepare('SELECT product_id, name, price, stock_quantity, sizes, sub_category, COALESCE(c.category_name, \'\') AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.product_id = ? LIMIT 1');
            $stmt->execute([(int) $contextProduct['product_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $contextProduct = $row;
            }
        } catch (Throwable $e) {
            // keep memory product
        }
    }

    if (!empty($grounding['stock_matches_db']) && is_array($contextProduct)) {
        $stock = verify_stock_reply_matches_db($reply, $contextProduct);
        $checks['stock_matches_db'] = $stock['passed'];
        if (!$stock['passed']) {
            foreach ($stock['errors'] as $err) {
                $errors[] = 'stock_grounding: ' . $err;
            }
        }
    }

    if (!empty($grounding['sizes_match_product']) && is_array($contextProduct)) {
        $reqSize = isset($grounding['requested_size']) ? (string) $grounding['requested_size'] : null;
        $size = verify_size_reply_matches_product($reply, $contextProduct, $reqSize);
        $checks['sizes_match_product'] = $size['passed'];
        if (!$size['passed']) {
            foreach ($size['errors'] as $err) {
                $errors[] = 'size_grounding: ' . $err;
            }
        }
    }

    if (!empty($grounding['policy_intent'])) {
        $policyIntent = (string) $grounding['policy_intent'];
        $minScore = (float) ($grounding['policy_min_score'] ?? 0.18);
        $policy = verify_policy_reply_grounded($reply, $policyIntent, $lang, $policyKnowledge, $minScore);
        $checks['policy_grounded'] = $policy['passed'];
        if (!$policy['passed']) {
            $errors[] = sprintf(
                'policy_grounding: overlap %.2f below threshold (matched %d/%d tokens)',
                $policy['score'],
                count($policy['matched']),
                $policy['expected_count']
            );
        }
    }

    if (!empty($grounding['prices_match_products']) && $products !== []) {
        $prices = verify_product_prices_grounded($reply, $products);
        $checks['prices_match_products'] = $prices['passed'];
        if (!$prices['passed']) {
            foreach ($prices['errors'] as $err) {
                $errors[] = 'price_grounding: ' . $err;
            }
        }
    }

    return ['passed' => $errors === [], 'errors' => $errors, 'checks' => $checks];
}

/**
 * Rule-based one-turn simulation (same routing as chatbot_api.php, no OpenAI).
 *
 * @return array{intent:string,reply:string,source:string,suggested_products:array,confidence:float,entities:array}
 */
function simulate_rule_based_turn(
    PDO $pdo,
    string $rawMessage,
    string $lang,
    array &$memory,
    array &$userProfile,
    ?int $chatUserId
): array {
    $policyKnowledge = load_policy_knowledge();

    $intent = detect_intent($rawMessage);
    $policyLockedIntent = detect_policy_lock_intent($rawMessage);
    if ($policyLockedIntent !== null) {
        $intent = $policyLockedIntent;
    }

    $entities = extract_entities($rawMessage);

    $lastSuggested = $memory['last_suggested_products'] ?? [];
    $hadProductContext = in_array(($memory['last_intent'] ?? ''), ['product_search', 'product_followup'], true)
        || (is_array($lastSuggested) && $lastSuggested !== []);

    if ($intent === 'general' && $hadProductContext) {
        $isSizeFollowup = !empty($entities['size'])
            || preg_match('/\b(sizes?|beden|numara)\b/ui', $rawMessage);
        $isStockFollowup = preg_match('/\b(stock|stok|stokta|in\s*stock|out\s*of\s*stock)\b/ui', $rawMessage)
            || (preg_match('/\b(available|availability)\b/ui', $rawMessage) && !preg_match('/\b(sizes?|beden)\b/ui', $rawMessage));
        $hasRefinementEntity = !empty($entities['max_price']) || !empty($entities['min_price']) || !empty($entities['category_like'])
            || !empty($entities['color']) || !empty($entities['size']) || !empty($entities['brand']);
        $hasRefinementWords = preg_match('/\b(those|these|only|sadece|olan|olanları|onlari|onları|goster|göster|daha ucuz|cheaper|black|white|siyah|beyaz|kablosuz|bluetooth|wireless|ucuz)\b/ui', $rawMessage);

        if ($isSizeFollowup || $isStockFollowup) {
            $intent = 'product_followup';
        } elseif ($hasRefinementEntity || $hasRefinementWords) {
            $intent = 'product_search';
        }
    }

    $intent = ($intent === 'general' && infer_product_search_intent($pdo, $rawMessage, $entities))
        ? 'product_search'
        : $intent;
    if ($policyLockedIntent !== null) {
        $intent = $policyLockedIntent;
    }

    $sessionCtx = apply_product_search_session_context($entities, $rawMessage, $intent, $memory, $userProfile);
    $entities = $sessionCtx['entities'];
    $userProfile = $sessionCtx['userProfile'];

    $intentConfidence = ($policyLockedIntent !== null)
        ? 0.96
        : ($intent === 'product_followup'
            ? 0.92
            : ((preg_match('/(\?|\b(how|what|where|when|why|nasıl|nasil|ne|nerede|ne zaman|neden)\b)/ui', $rawMessage) && $intent === 'general') ? 0.45 : 0.72));

    $memory['last_intent'] = $intent;
    $memory['entities'] = $entities;
    $memory['last_message'] = $rawMessage;
    $memory['last_cart'] = [];

    $entities['_raw_message'] = $rawMessage;
    [$reply, $suggestedProducts, , $responseSource, $actionEntities] = handle_intent_action(
        $pdo,
        $intent,
        $rawMessage,
        $lang,
        $policyKnowledge,
        $entities,
        $chatUserId,
        $memory
    );
    if (is_array($actionEntities ?? null) && $actionEntities !== []) {
        $entities = $actionEntities;
    }

    if (function_exists('policy_intent_has_product_leak') && policy_intent_has_product_leak($intent, $reply, $suggestedProducts, $responseSource)) {
        $intent = $policyLockedIntent ?: $intent;
        [$reply, $suggestedProducts, , $responseSource, $actionEntities] = handle_intent_action(
            $pdo,
            $intent,
            $rawMessage,
            $lang,
            $policyKnowledge,
            $entities,
            $chatUserId,
            $memory
        );
        if (is_array($actionEntities ?? null) && $actionEntities !== []) {
            $entities = $actionEntities;
        }
    }

    if (function_exists('enforce_response_consistency')) {
        [$reply, $suggestedProducts, $responseSource] = enforce_response_consistency(
            $pdo,
            $intent,
            $reply,
            $suggestedProducts,
            $entities,
            $lang,
            $responseSource
        );
    }

    if (!empty($suggestedProducts)) {
        $memory['last_suggested_products'] = array_slice($suggestedProducts, 0, 6);
        $prices = array_map(static fn($p) => (float) ($p['price'] ?? 0), $suggestedProducts);
        $prices = array_filter($prices, static fn($v) => $v > 0);
        if (!empty($prices)) {
            $memory['last_suggested_max_price'] = max($prices);
        }
    }

    $history = $memory['history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $history[] = ['role' => 'user', 'content' => $rawMessage];
    $history[] = ['role' => 'assistant', 'content' => $reply];
    $memory['history'] = array_slice($history, -10);

    $clarificationCount = (int) ($memory['clarification_count'] ?? 0);
    $confidence = estimate_confidence($intent, $responseSource, $suggestedProducts, $reply, false, $clarificationCount);
    $confidence = min($confidence, $intentConfidence);

    return [
        'intent' => $intent,
        'reply' => $reply,
        'source' => $responseSource,
        'suggested_products' => $suggestedProducts,
        'confidence' => round($confidence, 4),
        'entities' => $entities,
    ];
}

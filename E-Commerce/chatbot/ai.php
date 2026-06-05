<?php

function call_openai_chat(string $apiKey, string $userMessage, string $page, string $cartContext, array $products, array $history, string $policyContext, string $lang = "en", string $userName = ""): ?string
{
    $productLines = [];
    foreach ($products as $p) {
        $name = (string) ($p["name"] ?? "");
        $price = (float) ($p["price"] ?? 0);
        $cat = (string) ($p["category"] ?? "");
        if ($name !== "") $productLines[] = "- {$name} | {$cat} | {$price}";
    }
    $productContext = empty($productLines) ? "No product context." : implode("\n", $productLines);
    $system = "You are ZERA e-commerce assistant. Be concise, friendly, and sales-focused. Only mention products/categories that exist in provided context. If unsure, ask one clarifying question. Keep answers under 120 words. " . ($lang === "tr" ? "Always answer in Turkish." : "Always answer in English.");
    $nameContext = $userName !== "" ? "Customer first name: {$userName}\n" : "Customer first name: unknown\n";
    $context = "Current page: {$page}\n" . $nameContext . "Cart context:\n{$cartContext}\n\nProduct context:\n{$productContext}\n\nPolicy context:\n{$policyContext}";
    $messages = [["role" => "system", "content" => $system], ["role" => "system", "content" => $context]];
    $historyMessages = build_history_messages($history, 10);
    if (!empty($historyMessages)) $messages = array_merge($messages, $historyMessages);
    $messages[] = ["role" => "user", "content" => $userMessage];
    $payload = ["model" => "gpt-4o-mini", "temperature" => 0.5, "messages" => $messages];
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer {$apiKey}"], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 20]);
    $resp = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$resp || $httpCode < 200 || $httpCode >= 300) return null;
    $json = json_decode($resp, true);
    $content = $json["choices"][0]["message"]["content"] ?? null;
    return (is_string($content) && trim($content) !== "") ? trim($content) : null;
}

function normalize_intent(string $intent): string
{
    $intent = strtolower(trim($intent));
    $allowed = ["returns", "payment", "shipping", "order_status", "cart_question", "product_search", "product_followup", "general"];
    return in_array($intent, $allowed, true) ? $intent : "general";
}

function call_openai_intent_json(string $apiKey, string $userMessage): ?string
{
    $payload = [
        "model" => "gpt-4o-mini",
        "temperature" => 0,
        "messages" => [
            ["role" => "system", "content" => "Classify the user's latest message into one intent and return strict JSON only: {\"intent\":\"...\"}. Allowed: returns,payment,shipping,order_status,cart_question,product_search,general."],
            ["role" => "user", "content" => $userMessage],
        ],
    ];
    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer {$apiKey}"], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 12]);
    $resp = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$resp || $httpCode < 200 || $httpCode >= 300) return null;
    $json = json_decode($resp, true);
    $content = $json["choices"][0]["message"]["content"] ?? "";
    if (!is_string($content) || trim($content) === "") return null;
    $decoded = json_decode(trim($content), true);
    if (!is_array($decoded)) return null;
    return normalize_intent((string) ($decoded["intent"] ?? ""));
}

function resolve_intent(string $currentIntent, string $apiKey, string $rawMessage): string
{
    if ($currentIntent !== "general" || $apiKey === "") return $currentIntent;
    $aiIntent = call_openai_intent_json($apiKey, $rawMessage);
    return $aiIntent ?: $currentIntent;
}

function get_experiment_mode(): string
{
    $mode = strtolower((string) (getenv("CHATBOT_EXPERIMENT_MODE") ?: "ab2"));
    return in_array($mode, ["ab2", "ab", "ai", "rule"], true) ? $mode : "ab2";
}

function get_experiment_bucket(): string
{
    if (!isset($_SESSION["chatbot_bucket"])) $_SESSION["chatbot_bucket"] = (mt_rand(0, 1) === 1) ? "ai" : "rule";
    return (string) $_SESSION["chatbot_bucket"];
}

function should_use_ai(string $apiKey): bool
{
    if ($apiKey === "") return false;
    $mode = get_experiment_mode();
    if ($mode === "ab2") return true;
    if ($mode === "ai") return true;
    if ($mode === "rule") return false;
    return get_experiment_bucket() === "ai";
}

function get_experiment_variants(): array
{
    if (!isset($_SESSION["chatbot_exp1_variant"])) {
        $_SESSION["chatbot_exp1_variant"] = (mt_rand(0, 1) === 1) ? "A" : "B";
    }
    if (!isset($_SESSION["chatbot_exp2_variant"])) {
        $_SESSION["chatbot_exp2_variant"] = (mt_rand(0, 1) === 1) ? "A" : "B";
    }
    return [
        "exp1_guardrail" => (string) $_SESSION["chatbot_exp1_variant"], // A: normal, B: strict
        "exp2_fallback" => (string) $_SESSION["chatbot_exp2_variant"],  // A: clarify-first, B: direct-escalation
    ];
}

function get_reported_experiment_bucket(): string
{
    $mode = get_experiment_mode();
    if ($mode === "ab") {
        return get_experiment_bucket();
    }
    if ($mode === "ai") {
        return "ai";
    }
    if ($mode === "rule") {
        return "rule";
    }
    return "hybrid";
}

function is_ai_reply_grounded(string $reply, string $intent, array $products): bool
{
    $replyLower = to_lower($reply);

    // For non-product intents, block pricing/stock-like claims to reduce hallucinations.
    if ($intent !== "product_search") {
        if (preg_match('/(\$\s*\d+|\d+\s*(usd|dollar|tl|₺))/iu', $reply)) {
            return false;
        }
        if (preg_match('/\b(in stock|out of stock|stokta|stok yok)\b/iu', $replyLower)) {
            return false;
        }
        if (preg_match('/\b(recommend|suggest|öner|göz at|great deal|ürün öner|şunlara göz at|here are some)\b/ui', $replyLower)) {
            return false;
        }
        if (preg_match('/^\s*-\s.+\(\$\d+/m', $reply)) {
            return false;
        }
        foreach ($products as $p) {
            $name = to_lower((string) ($p['name'] ?? ''));
            if ($name !== '' && mb_strlen($name) >= 6 && str_contains($replyLower, $name)) {
                return false;
            }
        }
    }

    // If reply mentions explicit product prices, ensure they exist in provided product context.
    $allowedPrices = [];
    foreach ($products as $p) {
        $price = (float) ($p["price"] ?? 0);
        if ($price > 0) {
            $allowedPrices[] = number_format($price, 2, ".", "");
        }
    }
    if (preg_match_all('/\$\s*(\d+(?:\.\d{1,2})?)/u', $reply, $m)) {
        foreach ($m[1] as $priceStr) {
            $norm = number_format((float) $priceStr, 2, ".", "");
            if (!in_array($norm, $allowedPrices, true)) {
                return false;
            }
        }
    }

    // Keep responses concise; overly long responses tend to drift.
    $wordCount = str_word_count(strip_tags($reply));
    if ($wordCount > 140) {
        return false;
    }

    return true;
}

function is_ai_reply_grounded_strict(string $reply, string $intent, array $products): bool
{
    if (!is_ai_reply_grounded($reply, $intent, $products)) {
        return false;
    }

    $replyLower = to_lower($reply);
    if (preg_match('/\b(probably|maybe|i think|sanirim|galiba)\b/iu', $replyLower)) {
        return false;
    }

    $wordCount = str_word_count(strip_tags($reply));
    if ($wordCount > 90) {
        return false;
    }

    return true;
}


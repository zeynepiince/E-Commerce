<?php

function search_products_advanced(PDO $pdo, array $entities, int $limit = 4, int $relaxLevel = 0): array
{
    $sql = "SELECT p.product_id, p.name, p.price, p.image_url, COALESCE(c.category_name, '') AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE 1=1";
    $params = [];
    if (is_numeric($entities["max_price"])) { $sql .= " AND p.price <= ?"; $params[] = (float) $entities["max_price"]; }
    if (is_numeric($entities["min_price"])) { $sql .= " AND p.price >= ?"; $params[] = (float) $entities["min_price"]; }
    if (!empty($entities["category_like"])) {
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(c.category_name) LIKE ?)";
        $like = "%" . strtolower((string) $entities["category_like"]) . "%";
        $params[] = $like; $params[] = $like;
    }
    if (!empty($entities["keywords"])) {
        $orParts = [];
        foreach (array_slice($entities["keywords"], 0, 6) as $kw) {
            $orParts[] = "LOWER(p.name) LIKE ?";
            $orParts[] = "LOWER(c.category_name) LIKE ?";
            $like = "%" . to_lower((string) $kw) . "%";
            $params[] = $like; $params[] = $like;
        }
        if (!empty($orParts)) $sql .= " AND (" . implode(" OR ", $orParts) . ")";
    }
    if (!empty($entities["product_type"])) {
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(c.category_name) LIKE ?)";
        $like = "%" . to_lower((string) $entities["product_type"]) . "%";
        $params[] = $like;
        $params[] = $like;
    }
    if (($entities["audience"] ?? null) === "men") {
        $sql .= " AND (LOWER(c.category_name) LIKE ? OR LOWER(p.name) LIKE ?)
                  AND LOWER(p.name) NOT LIKE ?
                  AND LOWER(c.category_name) NOT LIKE ?";
        $params[] = "%men%";
        $params[] = "%men%";
        $params[] = "%women%";
        $params[] = "%women%";
    } elseif (($entities["audience"] ?? null) === "women") {
        $sql .= " AND (LOWER(c.category_name) LIKE ? OR LOWER(p.name) LIKE ?)
                  AND LOWER(p.name) NOT LIKE ?
                  AND LOWER(c.category_name) NOT LIKE ?";
        $params[] = "%women%";
        $params[] = "%women%";
        $params[] = "%men%";
        $params[] = "%men%";
    }
    if (!empty($entities["features"]) && is_array($entities["features"])) {
        foreach (array_slice($entities["features"], 0, 4) as $feature) {
            if ($feature === "noise_cancelling") {
                $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.name) LIKE ? OR LOWER(p.name) LIKE ?)";
                $params[] = "%noise%";
                $params[] = "%cancel%";
                $params[] = "%anc%";
                continue;
            }
            $sql .= " AND LOWER(p.name) LIKE ?";
            $params[] = "%" . to_lower((string) $feature) . "%";
        }
    }
    if (!empty($entities["color"])) { $sql .= " AND LOWER(p.name) LIKE ?"; $params[] = "%" . strtolower((string) $entities["color"]) . "%"; }
    if (!empty($entities["size"])) { $sql .= " AND LOWER(p.name) LIKE ?"; $params[] = "%" . strtolower((string) $entities["size"]) . "%"; }
    if (!empty($entities["brand"])) { $sql .= " AND LOWER(p.name) LIKE ?"; $params[] = "%" . strtolower((string) $entities["brand"]) . "%"; }
    $sortBy = $entities["sort_by"] ?? "featured_price";
    if ($sortBy === "price_asc") $sql .= " ORDER BY p.price ASC";
    elseif ($sortBy === "price_desc") $sql .= " ORDER BY p.price DESC";
    elseif ($sortBy === "newest") $sql .= " ORDER BY p.product_id DESC";
    else $sql .= " ORDER BY p.is_featured DESC, p.price ASC";
    $sql .= " LIMIT " . (int) $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($rows)) return $rows;
        if ($relaxLevel === 0) { $r = $entities; $r["color"] = null; $r["size"] = null; $r["brand"] = null; return search_products_advanced($pdo, $r, $limit, 1); }
        if ($relaxLevel === 1) { $r = $entities; $r["keywords"] = []; return search_products_advanced($pdo, $r, $limit, 2); }
        if ($relaxLevel === 2) { $r = $entities; $r["category_like"] = null; return search_products_advanced($pdo, $r, $limit, 3); }
        return [];
    } catch (Throwable $e) {
        return [];
    }
}

function infer_product_search_intent(PDO $pdo, string $rawMessage, array $entities): bool
{
    $text = trim($rawMessage);
    if ($text === "" || mb_strlen($text) < 2) return false;
    if (preg_match('/\b(hello|hi|thanks|thank you|merhaba|selam|teşekkür|tesekkur|yardım|help)\b/ui', $text)) return false;
    // Do not treat policy/support questions as product search.
    if (preg_match('/\b(return\w*|refund\w*|iade\w*|shipping|delivery|kargo\w*|teslim\w*|payment|ödeme\w*|odeme\w*|order\w*|sipariş\w*|siparis\w*|cancel\w*|iptal\w*)\b/ui', $text)) {
        return false;
    }
    $probe = $entities;
    if (empty($probe["keywords"])) {
        $tokens = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', to_lower($text))) ?: [];
        $stop = ["a","an","the","to","for","and","or","of","in","on","my","me","i","ve","ile","bir","bu","şu","su"];
        foreach ($tokens as $t) {
            $t = trim($t);
            if ($t === "" || mb_strlen($t) < 2 || in_array($t, $stop, true)) continue;
            $probe["keywords"][] = $t;
        }
        $probe["keywords"] = array_values(array_unique($probe["keywords"] ?? []));
    }
    return !empty(search_products_advanced($pdo, $probe, 1, 2));
}

function build_products_redirect_url(array $entities): string
{
    $params = [];
    if (is_numeric($entities["max_price"])) $params["max_price"] = (string) $entities["max_price"];
    if (is_numeric($entities["min_price"])) $params["min_price"] = (string) $entities["min_price"];
    $qParts = [];
    if (!empty($entities["category_like"])) $qParts[] = (string) $entities["category_like"];
    if (!empty($entities["color"])) $qParts[] = (string) $entities["color"];
    if (!empty($entities["brand"])) $qParts[] = (string) $entities["brand"];
    if (!empty($entities["product_type"])) $qParts[] = (string) $entities["product_type"];
    if (!empty($entities["features"]) && is_array($entities["features"])) {
        foreach (array_slice($entities["features"], 0, 3) as $f) $qParts[] = (string) $f;
    }
    if (!empty($entities["keywords"])) foreach (array_slice($entities["keywords"], 0, 3) as $kw) $qParts[] = (string) $kw;
    $qParts = array_values(array_unique(array_filter($qParts)));
    if (!empty($qParts)) $params["q"] = implode(" ", $qParts);
    if (!empty($entities["color"])) $params["color"] = (string) $entities["color"];
    if (!empty($entities["size"])) $params["size"] = (string) $entities["size"];
    if (!empty($entities["brand"])) $params["brand"] = (string) $entities["brand"];
    $qs = http_build_query($params);
    return "products.php" . ($qs !== "" ? ("?" . $qs) : "");
}

function try_budget_recommendation(PDO $pdo, string $rawMessage): ?string
{
    $msg = strtolower($rawMessage);
    $has = str_contains($msg, "shoe") || str_contains($msg, "shoes") || str_contains($msg, "sneaker") || str_contains($msg, "sneakers") || str_contains($msg, "running");
    if (!$has || !preg_match('/under\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) return null;
    $budget = (float) $m[1];
    if ($budget <= 0) return null;
    try {
        $stmt = $pdo->prepare("SELECT p.name, p.price FROM products p LEFT JOIN categories c ON c.category_id = p.category_id WHERE p.price <= ? AND (LOWER(p.name) LIKE '%shoe%' OR LOWER(p.name) LIKE '%sneaker%' OR LOWER(c.category_name) LIKE '%shoe%' OR LOWER(c.category_name) LIKE '%sneaker%' OR LOWER(c.category_name) LIKE '%running%') ORDER BY p.price ASC LIMIT 3");
        $stmt->execute([$budget]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) return "I couldn't find running shoes under $" . number_format($budget, 2) . ". Want me to suggest the closest options above your budget?";
        $lines = array_map(static fn($r) => "- " . $r["name"] . " ($" . number_format((float) $r["price"], 2) . ")", $rows);
        return "Great choice. Here are running shoes under $" . number_format($budget, 2) . ":\n" . implode("\n", $lines);
    } catch (Throwable $e) {
        return null;
    }
}

function fetch_top_products(PDO $pdo, int $limit = 6): array
{
    try {
        $stmt = $pdo->prepare("SELECT p.name, p.price, COALESCE(c.category_name, '') AS category FROM products p LEFT JOIN categories c ON c.category_id = p.category_id ORDER BY p.is_featured DESC, p.product_id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function handle_intent_action(PDO $pdo, string $intent, string $rawMessage, string $lang, array $policyKnowledge, array $entities): array
{
    $reply = "";
    $suggestedProducts = [];
    $redirectUrl = null;
    $source = "rule_based";
    if ($intent === "shipping") $reply = build_shipping_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "returns") $reply = build_returns_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "payment") $reply = build_payment_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "order_status") $reply = build_order_status_reply($rawMessage, $lang);
    elseif ($intent === "product_search") {
        $suggestedProducts = search_products_advanced($pdo, $entities, 4);
        $reply = build_product_reply($suggestedProducts, $entities, $lang);
        $redirectUrl = build_products_redirect_url($entities);
        $source = "product_search";
    } else {
        $budgetReply = try_budget_recommendation($pdo, $rawMessage);
        if (is_string($budgetReply) && $budgetReply !== "") { $reply = $budgetReply; $source = "budget_logic"; }
        else $reply = $lang === "tr" ? "Memnuniyetle yardımcı olurum. Ürün önerisi, kargo, iade veya sipariş takibi sorabilirsin." : "Happy to help. You can ask about product recommendations, shipping, returns, or order tracking.";
    }
    return [$reply, $suggestedProducts, $redirectUrl, $source];
}


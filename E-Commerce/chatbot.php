<?php
session_start();
require_once "db.php";
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"), true);
$rawMessage = trim((string) ($data["message"] ?? ""));
$message = function_exists("mb_strtolower") ? mb_strtolower($rawMessage, "UTF-8") : strtolower($rawMessage);
$cart = is_array($data["cart"] ?? null) ? $data["cart"] : [];
$page = (string) ($data["page"] ?? "");

if ($rawMessage === "") {
    echo json_encode(["reply" => "Please type a message first."]);
    exit;
}

function build_rule_based_reply(string $message, array $cart): string
{
    $reply = "I'm not sure I understood that. Can you rephrase?";

    if (str_contains($message, "hello") || str_contains($message, "hi")) {
        return "Hello! How can I help you today?";
    }
    if (str_contains($message, "price")) {
        return "You can find product prices on the product cards.";
    }
    if (str_contains($message, "order") || str_contains($message, "package") || str_contains($message, "shipment")) {
        return "Your last order is being prepared and will be shipped soon.";
    }
    if (str_contains($message, "help")) {
        return "Sure! I can help with products, orders, shipping, and returns.";
    }
    if (str_contains($message, "cart") || str_contains($message, "basket")) {
        if (!empty($cart)) {
            $count = 0;
            $names = [];
            foreach ($cart as $item) {
                $count += (int) ($item["qty"] ?? 1);
                $names[] = (string) ($item["name"] ?? "item");
            }
            $uniqueNames = implode(", ", array_unique($names));
            return "You currently have {$count} item(s) in your cart: {$uniqueNames}.";
        }
        return "Your cart looks empty right now. You can add products from the product cards.";
    }
    if (str_contains($message, "recommend") || str_contains($message, "suggest")) {
        return "Looking for recommendations? Tell me your budget and category, and I can suggest products.";
    }

    return $reply;
}

function detect_intent(string $message): string
{
    if (preg_match('/\b(return|refund|iade)\b/i', $message)) return "returns";
    if (preg_match('/\b(payment|pay|card|credit|debit|ödeme|kart)\b/i', $message)) return "payment";
    if (preg_match('/\b(shipping|cargo|kargo|delivery)\b/i', $message)) return "shipping";
    if (preg_match('/\b(order|package|shipment|siparis)\b/i', $message)) return "order_status";
    if (preg_match('/\b(cart|basket|sepet)\b/i', $message)) return "cart_question";
    if (preg_match('/\b(recommend|suggest|need|looking for|find|show|öner|oner|göster|goster|bul|ara)\b/ui', $message)) return "product_search";
    if (preg_match('/\b(ayakkabı|ayakkabi|koşu|kosu|sneaker|shoe|shoes|telefon|phone|elektronik|electronics)\b/ui', $message)) return "product_search";
    return "general";
}

function detect_language(string $rawMessage): string
{
    if (preg_match('/[çğıöşü]/iu', $rawMessage)) return "tr";
    if (preg_match('/\b(merhaba|selam|kargo|iade|sipariş|sepet|ayakkabı)\b/iu', mb_strtolower($rawMessage))) return "tr";
    return "en";
}

function load_policy_knowledge(): array
{
    $file = __DIR__ . "/knowledge/policies.php";
    if (!file_exists($file)) return [];
    $data = require $file;
    return is_array($data) ? $data : [];
}

function get_policy_reply(array $knowledge, string $intent, string $lang): ?string
{
    if (!isset($knowledge[$intent]) || !is_array($knowledge[$intent])) return null;
    $bucket = $knowledge[$intent];
    if (!empty($bucket[$lang]) && is_string($bucket[$lang])) return trim($bucket[$lang]);
    if (!empty($bucket["en"]) && is_string($bucket["en"])) return trim($bucket["en"]);
    return null;
}

function extract_entities(string $rawMessage): array
{
    $text = mb_strtolower($rawMessage);
    $out = [
        "budget" => ["min" => null, "max" => null],
        "max_price" => null,
        "min_price" => null,
        "category_like" => null,
        "color" => null,
        "size" => null,
        "brand" => null,
        "sort_by" => "featured_price",
        "keywords" => [],
    ];

    if (preg_match('/under\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $out["max_price"] = (float) $m[1];
    }
    if (preg_match('/below\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $out["max_price"] = (float) $m[1];
    }
    if (preg_match('/over\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $out["min_price"] = (float) $m[1];
    }
    if (preg_match('/above\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        $out["min_price"] = (float) $m[1];
    }
    $out["budget"]["min"] = $out["min_price"];
    $out["budget"]["max"] = $out["max_price"];

    if (preg_match('/\b(black|white|red|blue|green|gray|grey|brown|beige)\b/i', $rawMessage, $m)) {
        $out["color"] = strtolower($m[1]);
    }
    if (preg_match('/\b(siyah|beyaz|kırmızı|kirmizi|mavi|yeşil|yesil|gri|kahverengi|bej)\b/ui', $rawMessage, $m)) {
        $out["color"] = mb_strtolower($m[1]);
    }
    if (preg_match('/\b(?:size|numara)\s*(\d{1,2}(?:\.\d+)?)\b/i', $rawMessage, $m)) {
        $out["size"] = $m[1];
    }
    if (preg_match('/\bbrand\s+([a-z0-9\-]+)/i', $rawMessage, $m)) {
        $out["brand"] = strtolower($m[1]);
    }
    if (preg_match('/\b(cheapest|lowest|low to high|en ucuz|ucuz)\b/i', $rawMessage)) {
        $out["sort_by"] = "price_asc";
    } elseif (preg_match('/\b(expensive|high to low|premium|pahalı)\b/i', $rawMessage)) {
        $out["sort_by"] = "price_desc";
    } elseif (preg_match('/\b(newest|latest|yeni)\b/i', $rawMessage)) {
        $out["sort_by"] = "newest";
    }

    $categoryMap = [
        "running" => "running",
        "koşu" => "running",
        "shoe" => "shoe",
        "shoes" => "shoe",
        "ayakkabı" => "shoe",
        "ayakkabi" => "shoe",
        "sneaker" => "sneaker",
        "sneakers" => "sneaker",
        "electronics" => "electronics",
        "phone" => "phone",
        "telefon" => "phone",
        "women" => "women",
        "kadın" => "women",
        "kadin" => "women",
        "men" => "men",
        "erkek" => "men",
    ];
    foreach ($categoryMap as $token => $value) {
        if (str_contains($text, $token)) {
            $out["category_like"] = $value;
            break;
        }
    }

    $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text)) ?: [];
    $stop = ["i", "need", "under", "over", "show", "me", "for", "the", "a", "an", "to", "my", "ve", "ile", "olan", "olanları", "göster", "goster", "bana"];
    foreach ($words as $w) {
        $w = trim($w);
        if ($w === "" || strlen($w) < 3 || in_array($w, $stop, true) || is_numeric($w)) continue;
        $out["keywords"][] = $w;
    }
    $out["keywords"] = array_values(array_unique($out["keywords"]));

    if (empty($out["brand"])) {
        $brandCandidates = ["nike", "adidas", "puma", "reebok", "new balance", "asics", "converse"];
        foreach ($brandCandidates as $b) {
            if (str_contains($text, $b)) {
                $out["brand"] = $b;
                break;
            }
        }
    }

    return $out;
}

function search_products_advanced(PDO $pdo, array $entities, int $limit = 4, int $relaxLevel = 0): array
{
    $sql = "SELECT p.product_id, p.name, p.price, p.image_url, COALESCE(c.category_name, '') AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE 1=1";
    $params = [];

    if (is_numeric($entities["max_price"])) {
        $sql .= " AND p.price <= ?";
        $params[] = (float) $entities["max_price"];
    }
    if (is_numeric($entities["min_price"])) {
        $sql .= " AND p.price >= ?";
        $params[] = (float) $entities["min_price"];
    }

    if (!empty($entities["category_like"])) {
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(c.category_name) LIKE ?)";
        $like = "%" . strtolower((string) $entities["category_like"]) . "%";
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($entities["keywords"])) {
        $orParts = [];
        foreach (array_slice($entities["keywords"], 0, 6) as $kw) {
            $orParts[] = "LOWER(p.name) LIKE ?";
            $orParts[] = "LOWER(c.category_name) LIKE ?";
            $like = "%" . mb_strtolower((string) $kw) . "%";
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($orParts)) {
            $sql .= " AND (" . implode(" OR ", $orParts) . ")";
        }
    }

    if (!empty($entities["color"])) {
        $sql .= " AND LOWER(p.name) LIKE ?";
        $params[] = "%" . strtolower((string) $entities["color"]) . "%";
    }
    if (!empty($entities["size"])) {
        $sql .= " AND LOWER(p.name) LIKE ?";
        $params[] = "%" . strtolower((string) $entities["size"]) . "%";
    }
    if (!empty($entities["brand"])) {
        $sql .= " AND LOWER(p.name) LIKE ?";
        $params[] = "%" . strtolower((string) $entities["brand"]) . "%";
    }

    $sortBy = $entities["sort_by"] ?? "featured_price";
    if ($sortBy === "price_asc") {
        $sql .= " ORDER BY p.price ASC";
    } elseif ($sortBy === "price_desc") {
        $sql .= " ORDER BY p.price DESC";
    } elseif ($sortBy === "newest") {
        $sql .= " ORDER BY p.created_at DESC";
    } else {
        $sql .= " ORDER BY p.is_featured DESC, p.price ASC";
    }
    $sql .= " LIMIT " . (int) $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($rows)) return $rows;

        // Relax filters progressively to avoid false "not found"
        if ($relaxLevel === 0) {
            $relaxed = $entities;
            $relaxed["color"] = null;
            $relaxed["size"] = null;
            $relaxed["brand"] = null;
            return search_products_advanced($pdo, $relaxed, $limit, 1);
        }
        if ($relaxLevel === 1) {
            $relaxed = $entities;
            $relaxed["keywords"] = [];
            return search_products_advanced($pdo, $relaxed, $limit, 2);
        }
        if ($relaxLevel === 2) {
            $relaxed = $entities;
            $relaxed["category_like"] = null;
            return search_products_advanced($pdo, $relaxed, $limit, 3);
        }
        return [];
    } catch (Throwable $e) {
        return [];
    }
}

function build_product_reply(array $products, array $entities, string $lang = "en"): string
{
    if (empty($products)) {
        if (is_numeric($entities["max_price"])) {
            if ($lang === "tr") {
                return "$" . number_format((float) $entities["max_price"], 2) . " altında tam eşleşme bulamadım. Bütçeyi biraz artırıp tekrar deneyebilirsin.";
            }
            return "I couldn't find matches under $" . number_format((float) $entities["max_price"], 2) . ". Try a slightly higher budget or broader keywords.";
        }
        if ($lang === "tr") {
            return "Tam eşleşme bulamadım. Bütçe veya ürün tipi yazar mısın? (örn: 120 altı koşu ayakkabısı)";
        }
        return "I couldn't find matches. Try adding a budget or product type (for example: running shoes under 120).";
    }

    $lines = [];
    foreach ($products as $p) {
        $lines[] = "- {$p["name"]} ($" . number_format((float) $p["price"], 2) . ")";
    }
    if ($lang === "tr") {
        return "Sana uygun olabilecek seçenekler:\n" . implode("\n", $lines);
    }
    return "Here are some good options for you:\n" . implode("\n", $lines);
}

function build_products_redirect_url(array $entities): string
{
    $params = [];
    if (is_numeric($entities["max_price"])) {
        $params["max_price"] = (string) $entities["max_price"];
    }
    if (is_numeric($entities["min_price"])) {
        $params["min_price"] = (string) $entities["min_price"];
    }
    // Use search query instead of strict category to avoid false "no results".
    $qParts = [];
    if (!empty($entities["category_like"])) $qParts[] = (string) $entities["category_like"];
    if (!empty($entities["color"])) $qParts[] = (string) $entities["color"];
    if (!empty($entities["brand"])) $qParts[] = (string) $entities["brand"];
    if (!empty($entities["keywords"])) {
        foreach (array_slice($entities["keywords"], 0, 3) as $kw) $qParts[] = (string) $kw;
    }
    $qParts = array_values(array_unique(array_filter($qParts)));
    if (!empty($qParts)) {
        $params["q"] = implode(" ", $qParts);
    }

    // Keep extra entities for future UI filters/search.
    if (!empty($entities["color"])) {
        $params["color"] = (string) $entities["color"];
    }
    if (!empty($entities["size"])) {
        $params["size"] = (string) $entities["size"];
    }
    if (!empty($entities["brand"])) {
        $params["brand"] = (string) $entities["brand"];
    }

    $qs = http_build_query($params);
    return "products.php" . ($qs !== "" ? ("?" . $qs) : "");
}

function try_budget_recommendation(PDO $pdo, string $rawMessage): ?string
{
    $msg = strtolower($rawMessage);
    $hasProductIntent = str_contains($msg, "shoe")
        || str_contains($msg, "shoes")
        || str_contains($msg, "sneaker")
        || str_contains($msg, "sneakers")
        || str_contains($msg, "running");
    if (!$hasProductIntent) return null;

    if (!preg_match('/under\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) {
        return null;
    }
    $budget = (float) $m[1];
    if ($budget <= 0) return null;

    try {
        $stmt = $pdo->prepare(
            "SELECT p.name, p.price
             FROM products p
             LEFT JOIN categories c ON c.category_id = p.category_id
             WHERE p.price <= ?
               AND (
                 LOWER(p.name) LIKE '%shoe%'
                 OR LOWER(p.name) LIKE '%sneaker%'
                 OR LOWER(c.category_name) LIKE '%shoe%'
                 OR LOWER(c.category_name) LIKE '%sneaker%'
                 OR LOWER(c.category_name) LIKE '%running%'
               )
             ORDER BY p.price ASC
             LIMIT 3"
        );
        $stmt->execute([$budget]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return "I couldn't find running shoes under $" . number_format($budget, 2) . ". Want me to suggest the closest options above your budget?";
        }

        $lines = array_map(
            static fn($r) => "- " . $r["name"] . " ($" . number_format((float) $r["price"], 2) . ")",
            $rows
        );
        return "Great choice. Here are running shoes under $" . number_format($budget, 2) . ":\n" . implode("\n", $lines);
    } catch (Throwable $e) {
        return null;
    }
}

function fetch_top_products(PDO $pdo, int $limit = 6): array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT p.name, p.price, COALESCE(c.category_name, '') AS category
             FROM products p
             LEFT JOIN categories c ON c.category_id = p.category_id
             ORDER BY p.is_featured DESC, p.created_at DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function build_cart_context(array $cart): string
{
    if (empty($cart)) {
        return "Cart is empty.";
    }
    $lines = [];
    foreach ($cart as $item) {
        $name = (string) ($item["name"] ?? "Product");
        $qty = (int) ($item["qty"] ?? 1);
        $price = (float) ($item["price"] ?? 0);
        $lines[] = "- {$name} | qty: {$qty} | unit_price: {$price}";
    }
    return implode("\n", $lines);
}

function build_history_messages(array $history, int $maxMessages = 10): array
{
    if (empty($history)) return [];
    $trimmed = array_slice($history, -$maxMessages);
    $messages = [];
    foreach ($trimmed as $item) {
        if (!is_array($item)) continue;
        $role = $item["role"] ?? "";
        $content = trim((string) ($item["content"] ?? ""));
        if ($content === "" || ($role !== "user" && $role !== "assistant")) continue;
        $messages[] = ["role" => $role, "content" => $content];
    }
    return $messages;
}

function call_openai_chat(string $apiKey, string $userMessage, string $page, string $cartContext, array $products, array $history, string $lang = "en"): ?string
{
    $productLines = [];
    foreach ($products as $p) {
        $name = (string) ($p["name"] ?? "");
        $price = (float) ($p["price"] ?? 0);
        $cat = (string) ($p["category"] ?? "");
        if ($name !== "") {
            $productLines[] = "- {$name} | {$cat} | {$price}";
        }
    }
    $productContext = empty($productLines) ? "No product context." : implode("\n", $productLines);

    $system = "You are STORY e-commerce assistant. Be concise, friendly, and sales-focused. "
        . "Only mention products/categories that exist in provided context. "
        . "If unsure, ask one clarifying question. Keep answers under 120 words. "
        . ($lang === "tr" ? "Always answer in Turkish." : "Always answer in English.");

    $context = "Current page: {$page}\n"
        . "Cart context:\n{$cartContext}\n\n"
        . "Product context:\n{$productContext}";

    $historyMessages = build_history_messages($history, 10);
    $messages = [
        ["role" => "system", "content" => $system],
        ["role" => "system", "content" => $context],
    ];
    if (!empty($historyMessages)) {
        $messages = array_merge($messages, $historyMessages);
    }
    $messages[] = ["role" => "user", "content" => $userMessage];

    $payload = [
        "model" => "gpt-4o-mini",
        "temperature" => 0.5,
        "messages" => $messages,
    ];

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}",
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20,
    ]);

    $resp = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$resp || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $json = json_decode($resp, true);
    $content = $json["choices"][0]["message"]["content"] ?? null;
    if (!is_string($content) || trim($content) === "") {
        return null;
    }
    return trim($content);
}

$intent = detect_intent($message);
$lang = detect_language($rawMessage);
$policyKnowledge = load_policy_knowledge();
$entities = extract_entities($rawMessage);
$memory = $_SESSION["chatbot_memory"] ?? [];
if (!is_array($memory)) $memory = [];
$history = $memory["history"] ?? [];
if (!is_array($history)) $history = [];

// Carry over missing filters from previous product search turn
if ($intent === "product_search" && !empty($memory["entities"]) && is_array($memory["entities"])) {
    foreach (["max_price", "min_price", "category_like", "color", "size", "brand", "sort_by"] as $k) {
        if (empty($entities[$k]) && !empty($memory["entities"][$k])) {
            $entities[$k] = $memory["entities"][$k];
        }
    }
    if ((empty($entities["budget"]["min"]) && !empty($entities["min_price"])) || (empty($entities["budget"]["max"]) && !empty($entities["max_price"]))) {
        $entities["budget"]["min"] = $entities["min_price"] ?? null;
        $entities["budget"]["max"] = $entities["max_price"] ?? null;
    }
}

// Follow-up understanding: if previous turn was product_search, treat short refinements as product_search too.
if ($intent === "general" && (($memory["last_intent"] ?? "") === "product_search")) {
    $hasRefinementEntity = !empty($entities["max_price"]) || !empty($entities["min_price"]) || !empty($entities["category_like"]) || !empty($entities["color"]) || !empty($entities["size"]) || !empty($entities["brand"]);
    $hasRefinementWords = preg_match('/\b(those|these|only|sadece|olan|olanları|onlari|onları|goster|göster|daha ucuz|cheaper|black|white|siyah|beyaz)\b/ui', $rawMessage);
    if ($hasRefinementEntity || $hasRefinementWords) {
        $intent = "product_search";
    }
}

$reply = build_rule_based_reply($message, $cart);
$suggestedProducts = [];
$redirectUrl = null;

if ($intent === "shipping") {
    $reply = get_policy_reply($policyKnowledge, "shipping", $lang)
        ?? ($lang === "tr"
            ? "Kargo genelde 2-4 iş günü sürer. Belirli tutarın üzerindeki siparişlerde ücretsiz kargo olabilir."
            : "Shipping is usually 2-4 business days. Orders over a threshold may qualify for free shipping.");
} elseif ($intent === "returns") {
    $reply = get_policy_reply($policyKnowledge, "returns", $lang)
        ?? ($lang === "tr"
            ? "Çoğu ürünü, kullanılmamış ve orijinal durumdaysa 30 gün içinde iade edebilirsin."
            : "You can return most items within 30 days if unused and in original condition.");
} elseif ($intent === "payment") {
    $reply = get_policy_reply($policyKnowledge, "payment", $lang)
        ?? ($lang === "tr"
            ? "Ödeme bilgilerini güvenli altyapı ile işliyoruz."
            : "We process payment details through a secure infrastructure.");
} elseif ($intent === "product_search") {
    $suggestedProducts = search_products_advanced($pdo, $entities, 4);
    $reply = build_product_reply($suggestedProducts, $entities, $lang);
    $redirectUrl = build_products_redirect_url($entities);
} else {
    $budgetReply = try_budget_recommendation($pdo, $rawMessage);
    if (is_string($budgetReply) && $budgetReply !== "") {
        $reply = $budgetReply;
    }
}

$memory["last_intent"] = $intent;
$memory["entities"] = $entities;
$memory["last_message"] = $rawMessage;
$memory["last_cart"] = array_slice($cart, 0, 20);
$_SESSION["chatbot_memory"] = $memory;
$apiKey = getenv("OPENAI_API_KEY") ?: "";

if ($apiKey !== "") {
    $topProducts = fetch_top_products($pdo, 6);
    $cartContext = build_cart_context($cart);
    $aiReply = call_openai_chat($apiKey, $rawMessage, $page, $cartContext, $topProducts, $history, $lang);
    if (is_string($aiReply) && $aiReply !== "") {
        $reply = $aiReply;
        if ($intent === "product_search" && empty($suggestedProducts)) {
            $suggestedProducts = search_products_advanced($pdo, $entities, 4);
        }
    }
}

// Persist conversation history (last 10 messages total: user+assistant)
$history[] = ["role" => "user", "content" => $rawMessage];
$history[] = ["role" => "assistant", "content" => $reply];
$memory["history"] = array_slice($history, -10);
$_SESSION["chatbot_memory"] = $memory;

// LOG TO DB (do not block chatbot response on DB errors)
try {
    $user_id = !empty($_SESSION["user_id"]) ? (int) $_SESSION["user_id"] : null;

    // If there is no logged-in user, try first user to satisfy FK.
    if (!$user_id) {
        $uStmt = $pdo->query("SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1");
        $firstUserId = $uStmt ? $uStmt->fetchColumn() : false;
        $user_id = $firstUserId ? (int) $firstUserId : null;
    }

    // Only log when we have a valid user id.
    if ($user_id) {
        $stmt = $pdo->prepare("
          INSERT INTO support_interactions (user_id, message, sender)
          VALUES (?, ?, 'user')
        ");
        $stmt->execute([$user_id, $rawMessage]);

        $stmt = $pdo->prepare("
          INSERT INTO support_interactions (user_id, message, sender)
          VALUES (?, ?, 'bot')
        ");
        $stmt->execute([$user_id, $reply]);
    }
} catch (Throwable $e) {
    // Keep silent: chatbot must still return reply.
}

echo json_encode([
  "reply" => $reply,
  "intent" => $intent,
  "entities" => $entities,
  "suggested_products" => $suggestedProducts,
  "redirect_url" => $redirectUrl
]);

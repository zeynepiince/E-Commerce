<?php

function build_rule_based_reply(string $message, array $cart): string
{
    $reply = "I'm not sure I understood that. Can you rephrase?";
    if (str_contains($message, "hello") || str_contains($message, "hi")) return "Hello! How can I help you today?";
    if (str_contains($message, "price")) return "You can find product prices on the product cards.";
    if (str_contains($message, "order") || str_contains($message, "package") || str_contains($message, "shipment")) return "Your last order is being prepared and will be shipped soon.";
    if (str_contains($message, "help")) return "Sure! I can help with products, orders, shipping, and returns.";
    if (str_contains($message, "cart") || str_contains($message, "basket")) {
        if (!empty($cart)) {
            $count = 0;
            $names = [];
            foreach ($cart as $item) {
                $count += (int) ($item["qty"] ?? 1);
                $names[] = (string) ($item["name"] ?? "item");
            }
            return "You currently have {$count} item(s) in your cart: " . implode(", ", array_unique($names)) . ".";
        }
        return "Your cart looks empty right now. You can add products from the product cards.";
    }
    if (str_contains($message, "recommend") || str_contains($message, "suggest")) return "Looking for recommendations? Tell me your budget and category, and I can suggest products.";
    return $reply;
}

function load_policy_knowledge(): array
{
    $file = __DIR__ . "/../knowledge/policies.php";
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

function build_product_reply(array $products, array $entities, string $lang = "en"): string
{
    if (empty($products)) {
        if (isset($entities["max_price"]) && is_numeric($entities["max_price"])) {
            return $lang === "tr"
                ? "$" . number_format((float) $entities["max_price"], 2) . " altında tam eşleşme bulamadım. Bütçeyi biraz artırıp tekrar deneyebilirsin."
                : "I couldn't find matches under $" . number_format((float) $entities["max_price"], 2) . ". Try a slightly higher budget or broader keywords.";
        }
        return $lang === "tr"
            ? "Tam eşleşme bulamadım. Bütçe veya ürün tipi yazar mısın? (örn: 120 altı koşu ayakkabısı)"
            : "I couldn't find matches. Try adding a budget or product type (for example: running shoes under 120).";
    }
    $lines = [];

    foreach ($products as $p) {
        $name = $p["name"] ?? "Product";
        $price = number_format((float) ($p["price"] ?? 0), 2);
        $stock = (int) ($p["stock_quantity"] ?? 0);

        if ($stock <= 0) {
            continue;
        }

        $lines[] = "- " . $name . " ($" . $price . ")";
    }

    if (empty($lines)) {
        return $lang === "tr"
            ? "Şu anda stokta uygun ürün bulamadım. Farklı bir kategori veya bütçe deneyebilirsin."
            : "I couldn't find suitable in-stock products right now. Try another category or budget.";
    }
    return $lang === "tr" ? "Sana uygun olabilecek seçenekler:\n" . implode("\n", $lines) : "Here are some good options for you:\n" . implode("\n", $lines);
}

function build_shipping_reply(string $rawMessage, array $knowledge, string $lang): string
{
    $lower = to_lower($rawMessage);
    $base = get_policy_reply($knowledge, "shipping", $lang) ?? ($lang === "tr" ? "Kargo genelde 2-4 iş günü sürer." : "Shipping usually takes 2-4 business days.");
    $pick = static function (array $options): string { return $options[array_rand($options)]; };
    if (preg_match('/\b(free|ücretsiz)\b/ui', $lower)) return $lang === "tr" ? $pick(["Ücretsiz kargo kampanyaları sepet tutarına göre değişiyor. Ödeme adımında net olarak görebilirsin.","Bazı siparişlerde ücretsiz kargo var. Sepette eşik tutarı geçince otomatik uygulanır."]) : $pick(["Free shipping depends on your cart total and campaign rules. You can see it clearly at checkout.","Some orders qualify for free shipping. Once your cart reaches the threshold, it is applied automatically."]);
    if (preg_match('/\b(express|same day|hızlı|hizli|acil|fast)\b/ui', $lower)) return $lang === "tr" ? $pick(["Hızlı teslimat bazı bölgelerde aktif. Uygunsa ödeme adımında 'hızlı teslimat' seçeneği görünür.","Ekspres teslimat stok ve adrese göre değişiyor. Checkout ekranında uygun seçenekleri görürsün."]) : $pick(["Fast delivery is available in selected areas. If it's available for your address, you'll see it at checkout.","Express delivery depends on stock and destination. Checkout shows the fastest option for your order."]);
    if (preg_match('/\b(international|abroad|yurtdışı|yurtdisi)\b/ui', $lower)) return $lang === "tr" ? "Yurtdışı gönderim ülkeye göre değişir. Ürün ve adresine göre teslim süresi checkout'ta hesaplanır." : "International shipping varies by country. Delivery time is calculated at checkout based on product and destination.";
    if (preg_match('/\b(late|delay|gecik|where is my order|where is my cargo|nerede|track)\b/ui', $lower)) return $lang === "tr" ? $pick(["Kargonu takip etmek için Orders sayfasındaki 'Track Order' butonunu kullanabilirsin.","Siparişin yoldaysa en güncel hareketleri Orders > Track Order bölümünde görebilirsin."]) : $pick(["You can track your shipment from the Orders page using the Track Order button.","For real-time updates, open Orders and click Track Order on your latest purchase."]);
    if (preg_match('/\b(when|eta|estimated|arrive|arrival|teslim|ne zaman)\b/ui', $lower)) return $lang === "tr" ? $pick(["Tahmini teslimat çoğu siparişte 2-4 iş günü. Net tarih, sipariş detayında görünür.","Siparişin için tahmini teslim süresi genelde 2-4 iş günü; kesin ETA’yı Orders sayfasında görebilirsin."]) : $pick(["Estimated delivery is usually 2-4 business days. You can see the exact ETA in your order details.","Most orders arrive within 2-4 business days. For your exact date, check the Orders page."]);
    return $base;
}

function build_order_status_reply(string $rawMessage, string $lang): string
{
    $lower = to_lower($rawMessage);
    $pick = static function (array $options): string { return $options[array_rand($options)]; };
    if (preg_match('/\b(when|eta|estimated|arrive|arrival|teslim|ne zaman)\b/ui', $lower)) return $lang === "tr" ? $pick(["Siparişlerin çoğu 2-4 iş gününde teslim ediliyor. Güncel ETA’yı Orders sayfasında görebilirsin.","Tahmini teslim tarihi sipariş detayında yer alır; genelde 2-4 iş günü içinde teslim edilir."]) : $pick(["Most orders arrive within 2-4 business days. You can see your exact ETA on the Orders page.","Your estimated delivery date is shown in order details; typical delivery is 2-4 business days."]);
    return $lang === "tr" ? $pick(["Son siparişin hazırlanıyor. Kargoya verildiğinde takip bilgisi Orders sayfasında görünecek.","Siparişin işleme alındı. Durumu ve takip bilgisini Orders > Track Order üzerinden izleyebilirsin."]) : $pick(["Your latest order is being prepared. Tracking details will appear in Orders once it ships.","Your order is in processing. You can follow status updates from Orders > Track Order."]);
}

function build_returns_reply(string $rawMessage, array $knowledge, string $lang): string
{
    $lower = to_lower($rawMessage);
    $base = get_policy_reply($knowledge, "returns", $lang) ?? ($lang === "tr" ? "Çoğu ürünü, kullanılmamış ve orijinal durumdaysa 30 gün içinde iade edebilirsin." : "You can return most items within 30 days if unused and in original condition.");
    $pick = static function (array $options): string { return $options[array_rand($options)]; };
    if (preg_match('/\b(how|nasıl|nasil|process|adım|adim)\b/ui', $lower)) return $lang === "tr" ? $pick(["İade için Orders sayfasında ilgili siparişe girip iade talebi başlatabilirsin.","İade adımları: sipariş detayından talep aç → ürünü gönder → kontrol sonrası ücret iadesi."]) : $pick(["To return an item, open Orders, select the order, and start a return request.","Return flow: open order details, submit return request, ship the item, and receive refund after inspection."]);
    if (preg_match('/\b(refund|ücret|ucret|money back)\b/ui', $lower)) return $lang === "tr" ? "Ücret iadesi, ürün kontrolünden sonra ödeme yöntemine göre birkaç iş günü içinde tamamlanır." : "Refunds are processed after item inspection and usually completed within a few business days.";
    return $base;
}

function build_payment_reply(string $rawMessage, array $knowledge, string $lang): string
{
    $lower = to_lower($rawMessage);
    $base = get_policy_reply($knowledge, "payment", $lang) ?? ($lang === "tr" ? "Ödeme bilgilerini güvenli altyapı ile işliyoruz." : "We process payment details through a secure infrastructure.");
    if (preg_match('/\b(installment|taksit)\b/ui', $lower)) return $lang === "tr" ? "Taksit seçenekleri kartına ve banka kampanyasına göre checkout adımında gösterilir." : "Installment options are shown at checkout based on your card and bank campaign.";
    if (preg_match('/\b(card|kart|credit|debit)\b/ui', $lower)) return $lang === "tr" ? "Kart bilgilerin şifreli olarak işlenir ve sistemde açık metin olarak saklanmaz." : "Card details are processed securely and are not stored as plain text in the system.";
    return $base;
}

function build_cart_context(array $cart): string
{
    if (empty($cart)) return "Cart is empty.";
    $lines = [];
    foreach ($cart as $item) $lines[] = "- " . (string) ($item["name"] ?? "Product") . " | qty: " . (int) ($item["qty"] ?? 1) . " | unit_price: " . (float) ($item["price"] ?? 0);
    return implode("\n", $lines);
}

function build_history_messages(array $history, int $maxMessages = 10): array
{
    if (empty($history)) return [];
    $messages = [];
    foreach (array_slice($history, -$maxMessages) as $item) {
        if (!is_array($item)) continue;
        $role = $item["role"] ?? "";
        $content = trim((string) ($item["content"] ?? ""));
        if ($content !== "" && ($role === "user" || $role === "assistant")) $messages[] = ["role" => $role, "content" => $content];
    }
    return $messages;
}

function build_policy_context(array $knowledge, string $lang): string
{
    $keys = ["shipping", "returns", "payment"];
    $lines = [];
    foreach ($keys as $k) {
        if (!isset($knowledge[$k]) || !is_array($knowledge[$k])) continue;
        $txt = (!empty($knowledge[$k][$lang]) && is_string($knowledge[$k][$lang])) ? trim($knowledge[$k][$lang]) : ((!empty($knowledge[$k]["en"]) && is_string($knowledge[$k]["en"])) ? trim($knowledge[$k]["en"]) : null);
        if ($txt) $lines[] = strtoupper($k) . ": " . $txt;
    }
    return empty($lines) ? "No explicit policy context." : implode("\n", $lines);
}

function estimate_confidence(
    string $intent,
    string $responseSource,
    array $suggestedProducts,
    string $reply,
    bool $guardrailRejected = false,
    int $clarificationCount = 0
): float
{
    $confidence = 0.55;
    if ($responseSource === "ai") $confidence = 0.8;
    if ($responseSource === "product_search") $confidence = 0.75;
    if ($responseSource === "budget_logic") $confidence = 0.72;
    if ($responseSource === "rule_based") $confidence = 0.58;
    if ($responseSource === "rate_limit") $confidence = 0.99;
    if ($intent === "product_search") $confidence += !empty($suggestedProducts) ? 0.1 : -0.15;
    if (strlen(trim($reply)) < 16) $confidence -= 0.12;

    $wordCount = str_word_count(strip_tags(trim($reply)));
    if ($wordCount >= 8 && $wordCount <= 90) $confidence += 0.04;
    if ($wordCount < 6) $confidence -= 0.08;
    if ($wordCount > 130) $confidence -= 0.1;

    if ($guardrailRejected) $confidence -= 0.2;
    if ($clarificationCount > 0) $confidence -= min(0.15, $clarificationCount * 0.05);

    return max(0.05, min(0.99, $confidence));
}

function maybe_add_escalation(string $reply, float $confidence, string $lang): array
{
    if ($confidence >= 0.55) return [$reply, false];
    $suffix = $lang === "tr" ? "\n\nİstersen seni canlı destek ekibine yönlendirebilirim." : "\n\nIf you want, I can connect you to a human support agent.";
    return [$reply . $suffix, true];
}

function build_clarification_question(string $intent, string $lang): string
{
    if ($intent === "product_search") {
        return $lang === "tr"
            ? "Daha iyi önerebilmem için hangi fiyat aralığında bakıyorsunuz?"
            : "To refine recommendations, what price range are you looking at?";
    }
    if ($intent === "shipping" || $intent === "order_status") {
        return $lang === "tr"
            ? "Sipariş durumunu kontrol etmem için sipariş numaranızı veya yaklaşık tarihini paylaşır mısınız?"
            : "To check status more precisely, could you share your order number or approximate order date?";
    }
    return $lang === "tr"
        ? "Size doğru yardımcı olabilmem için soruyu biraz daha detaylandırır mısınız?"
        : "Could you add a bit more detail so I can help accurately?";
}


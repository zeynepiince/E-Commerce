<?php

function build_rule_based_reply(string $message, array $cart): string
{
    $reply = "I'm not sure I understood that. Can you rephrase?";
    if (str_contains($message, "hello") || str_contains($message, "hi")) return "Hello! How can I help you today?";
    if (str_contains($message, "price")) return "You can find product prices on the product cards.";
    if (str_contains($message, "order") || str_contains($message, "package") || str_contains($message, "shipment")) return "Your last order is being prepared and will be shipped soon.";
    if (str_contains($message, "help")) return "Sure! I can help with products, orders, shipping, and returns.";
    if (str_contains($message, "cart") || str_contains($message, "basket")) {
        return build_cart_reply($cart, "en");
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

function format_budget_limit_label(array $entities, string $lang): string
{
    $currency = strtoupper((string) ($entities['budget']['currency'] ?? 'USD'));
    $amount = $entities['budget']['max'] ?? $entities['budget']['min'] ?? null;
    if (!is_numeric($amount) && is_numeric($entities['max_price'] ?? null)) {
        $amount = (float) $entities['max_price'];
        $currency = 'USD';
    }
    if (!is_numeric($amount)) {
        return $lang === 'tr' ? 'bu bütçe' : 'that budget';
    }
    $amount = (float) $amount;
    $formatted = fmod($amount, 1.0) === 0.0
        ? number_format($amount, 0)
        : number_format($amount, 2);
    if ($currency === 'EUR') {
        return $lang === 'tr'
            ? $formatted . ' euro'
            : '€' . $formatted;
    }
    if ($currency === 'TRY') {
        return $formatted . ($lang === 'tr' ? ' TL' : ' TRY');
    }
    return '$' . $formatted;
}

function build_product_reply_intro(array $entities, string $lang = "en"): string
{
    if (!empty($entities["_cheaper_refinement"])) {
        return $lang === "tr"
            ? "Daha uygun fiyatlı seçenekler:"
            : "Here are more affordable options:";
    }
    if (!empty($entities["_best_sellers_request"])) {
        return $lang === "tr"
            ? "En çok satan ürünler:"
            : "Best sellers:";
    }
    $fallback = (string) ($entities["_audience_fallback"] ?? "");
    if ($fallback === "women_shirt") {
        return $lang === "tr"
            ? "Şu an kadın tişört stokta yok; tişört kategorisindeki diğer seçenekler:"
            : "We don't have women's t-shirts in stock right now; here are other shirt options:";
    }
    if ($fallback === "men_shirt") {
        return $lang === "tr"
            ? "Erkek tişört seçenekleri:"
            : "Men's shirt options:";
    }
    if ($fallback === "women_dress") {
        return $lang === "tr"
            ? "Şu an kadın elbise stokta yok; benzer giyim seçenekleri:"
            : "We don't have women's dresses in stock right now; here are similar clothing options:";
    }
    if (!empty($entities["_audience_correction"])) {
        return $lang === "tr"
            ? "Anladım, kadın ürünleri arıyorsun:"
            : "Got it — looking for women's items:";
    }
    return $lang === "tr"
        ? "Sana uygun olabilecek seçenekler:"
        : "Here are some good options for you:";
}

function build_product_reply(array $products, array $entities, string $lang = "en"): string
{
    if (empty($products)) {
        if (isset($entities["max_price"]) && is_numeric($entities["max_price"])) {
            $label = format_budget_limit_label($entities, $lang);
            return $lang === "tr"
                ? $label . " altında tam eşleşme bulamadım. Bütçeyi biraz artırıp tekrar deneyebilirsin."
                : "I couldn't find matches under " . $label . ". Try a slightly higher budget or broader keywords.";
        }
        $type = function_exists('to_lower')
            ? to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''))
            : strtolower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));
        if ($type === 'skirts') {
            return $lang === 'tr'
                ? 'Şu an katalogda etek bulamadım. Tişört, ayakkabı veya başka bir kategori deneyebilirsin.'
                : "I couldn't find skirts in the catalog right now. Try another category like shirts or shoes.";
        }
        if ($type === 'dress') {
            return $lang === 'tr'
                ? 'Şu an katalogda elbise bulamadım. Tişört, ayakkabı veya başka bir kategori deneyebilirsin.'
                : "I couldn't find dresses in the catalog right now. Try another category like shirts or shoes.";
        }
        if (in_array($type, ['fiction', 'non-fiction'], true)) {
            return $lang === 'tr'
                ? 'Şu an katalogda kitap bulamadım. Başka bir kategori deneyebilirsin.'
                : "I couldn't find books in the catalog right now. Try another category.";
        }
        if ($type === 'camera') {
            return $lang === 'tr'
                ? 'Şu an katalogda kamera bulamadım. Telefon veya elektronik kategorisine göz atabilirsin.'
                : "I couldn't find cameras in the catalog right now. Try phones or electronics instead.";
        }
        $audience = function_exists('to_lower')
            ? to_lower((string) ($entities['audience'] ?? ''))
            : strtolower((string) ($entities['audience'] ?? ''));
        if ($type === 'shirt' && $audience === 'women') {
            if (!empty($entities['_audience_correction']) || !empty($entities['_strict_audience'])) {
                return $lang === 'tr'
                    ? 'Haklısın — şu an katalogda kadın tişört yok. Kadın giyim ürünleri eklendiğinde burada göstereceğim.'
                    : "You're right — we don't have women's t-shirts in the catalog yet. I'll show them here once they're added.";
            }
            return $lang === 'tr'
                ? 'Şu an katalogda kadın tişört bulamadım. Kadın giyim ürünleri eklendiğinde burada göstereceğim; şimdilik başka bir kategori deneyebilirsin.'
                : "I couldn't find women's t-shirts in the catalog right now. Try another category for now.";
        }
        if (in_array($audience, ['women', 'men'], true) && !empty($entities['_audience_correction'])) {
            $label = $audience === 'women'
                ? ($lang === 'tr' ? 'kadın' : "women's")
                : ($lang === 'tr' ? 'erkek' : "men's");
            return $lang === 'tr'
                ? "Haklısın — şu an katalogda {$label} ürün bulamadım. Bu kategori eklendiğinde burada göstereceğim."
                : "You're right — we don't have {$label} items in the catalog yet. I'll show them here once they're added.";
        }
        if ($type === 'shirt') {
            return $lang === 'tr'
                ? 'Şu an katalogda tişört bulamadım. Ayakkabı veya başka bir kategori deneyebilirsin.'
                : "I couldn't find shirts in the catalog right now. Try shoes or another category.";
        }
        return $lang === "tr"
            ? "Tam eşleşme bulamadım. Bütçe veya ürün tipi yazar mısın? (örn: 100 euro altı koşu ayakkabısı)"
            : "I couldn't find matches. Try adding a budget or product type (for example: running shoes under €100).";
    }
    $hasInStock = false;
    foreach ($products as $p) {
        if ((int) ($p["stock_quantity"] ?? 0) > 0) {
            $hasInStock = true;
            break;
        }
    }

    if (!$hasInStock) {
        return $lang === "tr"
            ? "Şu anda stokta uygun ürün bulamadım. Farklı bir kategori veya bütçe deneyebilirsin."
            : "I couldn't find suitable in-stock products right now. Try another category or budget.";
    }

    // Product cards are rendered separately in the chat UI.
    return build_product_reply_intro($entities, $lang);
}

function build_shipping_reply(string $rawMessage, array $knowledge, string $lang): string
{
    $lower = to_lower($rawMessage);
    $base = get_policy_reply($knowledge, "shipping", $lang) ?? ($lang === "tr" ? "Kargo genelde 2-4 iş günü sürer." : "Shipping usually takes 2-4 business days.");
    if (preg_match('/\b(free|ücretsiz)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["Ücretsiz kargo kampanyaları sepet tutarına göre değişiyor. Ödeme adımında net olarak görebilirsin.","Bazı siparişlerde ücretsiz kargo var. Sepette eşik tutarı geçince otomatik uygulanır."], $rawMessage) : pick_deterministic_reply(["Free shipping depends on your cart total and campaign rules. You can see it clearly at checkout.","Some orders qualify for free shipping. Once your cart reaches the threshold, it is applied automatically."], $rawMessage);
    if (preg_match('/\b(express|same day|hızlı|hizli|acil|fast)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["Hızlı teslimat bazı bölgelerde aktif. Uygunsa ödeme adımında 'hızlı teslimat' seçeneği görünür.","Ekspres teslimat stok ve adrese göre değişiyor. Checkout ekranında uygun seçenekleri görürsün."], $rawMessage) : pick_deterministic_reply(["Fast delivery is available in selected areas. If it's available for your address, you'll see it at checkout.","Express delivery depends on stock and destination. Checkout shows the fastest option for your order."], $rawMessage);
    if (preg_match('/\b(international|abroad|yurtdışı|yurtdisi)\b/ui', $lower)) return $lang === "tr" ? "Yurtdışı gönderim ülkeye göre değişir. Ürün ve adresine göre teslim süresi checkout'ta hesaplanır." : "International shipping varies by country. Delivery time is calculated at checkout based on product and destination.";
    if (preg_match('/\b(late|delay|gecik|where is my order|where is my cargo|nerede|track)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["Kargonu takip etmek için Orders sayfasındaki 'Track Order' butonunu kullanabilirsin.","Siparişin yoldaysa en güncel hareketleri Orders > Track Order bölümünde görebilirsin."], $rawMessage) : pick_deterministic_reply(["You can track your shipment from the Orders page using the Track Order button.","For real-time updates, open Orders and click Track Order on your latest purchase."], $rawMessage);
    if (preg_match('/\b(when|eta|estimated|arrive|arrival|teslim|ne zaman)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["Tahmini teslimat çoğu siparişte 2-4 iş günü. Net tarih, sipariş detayında görünür.","Siparişin için tahmini teslim süresi genelde 2-4 iş günü; kesin ETA’yı Orders sayfasında görebilirsin."], $rawMessage) : pick_deterministic_reply(["Estimated delivery is usually 2-4 business days. You can see the exact ETA in your order details.","Most orders arrive within 2-4 business days. For your exact date, check the Orders page."], $rawMessage);
    return $base;
}

function build_order_status_reply(string $rawMessage, string $lang): string
{
    $lower = to_lower($rawMessage);
    if (preg_match('/\b(when|eta|estimated|arrive|arrival|teslim|ne zaman)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["Siparişlerin çoğu 2-4 iş gününde teslim ediliyor. Güncel ETA’yı Orders sayfasında görebilirsin.","Tahmini teslim tarihi sipariş detayında yer alır; genelde 2-4 iş günü içinde teslim edilir."], $rawMessage) : pick_deterministic_reply(["Most orders arrive within 2-4 business days. You can see your exact ETA on the Orders page.","Your estimated delivery date is shown in order details; typical delivery is 2-4 business days."], $rawMessage);
    return $lang === "tr" ? pick_deterministic_reply(["Son siparişin hazırlanıyor. Kargoya verildiğinde takip bilgisi Orders sayfasında görünecek.","Siparişin işleme alındı. Durumu ve takip bilgisini Orders > Track Order üzerinden izleyebilirsin."], $rawMessage) : pick_deterministic_reply(["Your latest order is being prepared. Tracking details will appear in Orders once it ships.","Your order is in processing. You can follow status updates from Orders > Track Order."], $rawMessage);
}

function build_returns_reply(string $rawMessage, array $knowledge, string $lang): string
{
    $lower = to_lower($rawMessage);
    $base = get_policy_reply($knowledge, "returns", $lang) ?? ($lang === "tr" ? "Çoğu ürünü, kullanılmamış ve orijinal durumdaysa 30 gün içinde iade edebilirsin." : "You can return most items within 30 days if unused and in original condition.");
    if (preg_match('/\b(how|nasıl|nasil|process|adım|adim)\b/ui', $lower)) return $lang === "tr" ? pick_deterministic_reply(["İade için Orders sayfasında ilgili siparişe girip iade talebi başlatabilirsin.","İade adımları: sipariş detayından talep aç → ürünü gönder → kontrol sonrası ücret iadesi."], $rawMessage) : pick_deterministic_reply(["To return an item, open Orders, select the order, and start a return request.","Return flow: open order details, submit return request, ship the item, and receive refund after inspection."], $rawMessage);
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

function build_cart_reply(array $cart, string $lang = "en"): string
{
    $lines = [];
    $unitCount = 0;
    $total = 0.0;

    foreach ($cart as $item) {
        if (!is_array($item)) {
            continue;
        }
        $qty = max(1, (int) ($item["qty"] ?? 1));
        $unitCount += $qty;
        $name = trim((string) ($item["name"] ?? ""));
        if ($name === "") {
            $name = $lang === "tr" ? "Ürün" : "Product";
        }
        $price = (float) ($item["price"] ?? 0);
        $total += $price * $qty;
        $size = trim((string) ($item["size"] ?? ""));
        $label = $name;
        if ($size !== "") {
            $label .= " ({$size})";
        }
        if ($qty > 1) {
            $label .= " ×{$qty}";
        }
        $lines[] = $label;
    }

    if ($lines === []) {
        return $lang === "tr"
            ? "Sepetin şu an boş. Ürün kartlarından sepete ekleyebilirsin."
            : "Your cart is empty. You can add products from the product cards.";
    }

    $totalLabel = number_format($total, 2);
    if ($lang === "tr") {
        return "Sepetinde {$unitCount} ürün var:\n- " . implode("\n- ", $lines) . "\nToplam: \${$totalLabel}";
    }

    return "You have {$unitCount} item(s) in your cart:\n- " . implode("\n- ", $lines) . "\nTotal: \${$totalLabel}";
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

function confidence_low_threshold(): float
{
    return 0.55;
}

function confidence_did_you_mean_threshold(): float
{
    return 0.65;
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
    $sourceScores = [
        "ai" => 0.8,
        "product_search" => 0.75,
        "product_followup" => 0.86,
        "order_lookup" => 0.78,
        "budget_logic" => 0.72,
        "rule_based" => 0.58,
        "rate_limit" => 0.99,
    ];
    $confidence = $sourceScores[$responseSource] ?? 0.55;

    if ($intent === "product_search") {
        $confidence += !empty($suggestedProducts) ? 0.1 : -0.15;
    }
    if ($intent === "product_followup" && preg_match('/\b(in stock|out of stock|available|mevcut|stokta|beden)\b/ui', $reply)) {
        $confidence += 0.06;
    }
    if (in_array($intent, ["shipping", "returns", "payment"], true) && $responseSource === "rule_based" && strlen(trim($reply)) >= 24) {
        $confidence += 0.08;
    }
    if ($intent === "order_status" && $responseSource === "order_lookup") {
        $confidence += 0.06;
    }
    if (strlen(trim($reply)) < 16) {
        $confidence -= 0.12;
    }

    $wordCount = str_word_count(strip_tags(trim($reply)));
    if ($wordCount >= 8 && $wordCount <= 90) {
        $confidence += 0.04;
    }
    if ($wordCount < 6) {
        $confidence -= 0.08;
    }
    if ($wordCount > 130) {
        $confidence -= 0.1;
    }

    if ($guardrailRejected) {
        $confidence -= 0.2;
    }
    if ($clarificationCount > 0) {
        $confidence -= min(0.15, $clarificationCount * 0.05);
    }

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
    if ($intent === "product_followup") {
        return $lang === "tr"
            ? "Hangi ürün için beden veya stok bilgisi istiyorsunuz?"
            : "Which product do you want size or stock information for?";
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

function build_did_you_mean_suggestions(string $intent, string $lang): array
{
    if ($intent === "product_search") {
        return [
            $lang === "tr" ? "100 euro altı öner" : "Suggest items under €100",
            $lang === "tr" ? "Kablosuz modelleri göster" : "Show wireless options",
            $lang === "tr" ? "En popüler ürünler" : "Show most popular products",
        ];
    }
    if ($intent === "product_followup") {
        return [
            $lang === "tr" ? "S beden var mı?" : "Does it have size S?",
            $lang === "tr" ? "Stokta mı?" : "Is it in stock?",
            $lang === "tr" ? "Başka tişört öner" : "Suggest another t-shirt",
        ];
    }
    if ($intent === "shipping" || $intent === "order_status") {
        return [
            $lang === "tr" ? "Siparişim nerede?" : "Where is my order?",
            $lang === "tr" ? "Tahmini teslim tarihi" : "Estimated delivery date",
            $lang === "tr" ? "Kargo ücreti ne kadar?" : "How much is shipping?",
        ];
    }
    return [
        $lang === "tr" ? "Ürün öner" : "Suggest products",
        $lang === "tr" ? "İade koşulları neler?" : "What is your return policy?",
        $lang === "tr" ? "Ödeme yöntemleri neler?" : "What payment methods do you support?",
    ];
}


<?php

function detect_intent(string $message): string
{
    if (preg_match('/\b(return\w*|refund\w*|iade\w*)\b/ui', $message)) return "returns";
    if (preg_match('/\b(payment|pay\w*|card|credit|debit|ödeme\w*|odeme\w*|kart\w*)\b/ui', $message)) return "payment";
    if (preg_match('/\b(shipping|cargo|delivery|kargo\w*|teslim\w*|gönderi\w*|gonderi\w*)\b/ui', $message)) return "shipping";
    if (preg_match('/\b(order\w*|package\w*|shipment\w*|sipariş\w*|siparis\w*)\b/ui', $message)) return "order_status";
    if (preg_match('/\b(cart|basket|sepet\w*)\b/ui', $message)) return "cart_question";
    if (preg_match('/\b(recommend|suggest|need|looking for|find|show|öner|oner|göster|goster|bul|ara)\b/ui', $message)) return "product_search";
    return "general";
}

function detect_language(string $rawMessage): string
{
    if (preg_match('/[çğıöşü]/iu', $rawMessage)) return "tr";
    if (preg_match('/\b(merhaba|selam|kargo\w*|iade\w*|sipariş\w*|siparis\w*|sepet\w*|teşekkür|tesekkur)\b/iu', to_lower($rawMessage))) return "tr";
    return "en";
}

function detect_policy_lock_intent(string $message): ?string
{
    if (preg_match('/\b(return\w*|refund\w*|iade\w*)\b/ui', $message)) return "returns";
    if (preg_match('/\b(payment|pay\w*|card|credit|debit|ödeme\w*|odeme\w*|kart\w*)\b/ui', $message)) return "payment";
    if (preg_match('/\b(order\w*|package\w*|shipment\w*|sipariş\w*|siparis\w*)\b/ui', $message)) return "order_status";
    if (preg_match('/\b(shipping|cargo|delivery|kargo\w*|teslim\w*|gönderi\w*|gonderi\w*)\b/ui', $message)) return "shipping";
    return null;
}

function extract_entities(string $rawMessage): array
{
    $text = to_lower($rawMessage);
    $out = [
        "budget" => ["min" => null, "max" => null, "currency" => "USD"],
        "max_price" => null,
        "min_price" => null,
        "category_like" => null,
        "audience" => null,
        "product_type" => null,
        "features" => [],
        "color" => null,
        "size" => null,
        "brand" => null,
        "sort_by" => "featured_price",
        "keywords" => []
    ];
    if (preg_match('/under\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m) || preg_match('/below\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) $out["max_price"] = (float) $m[1];
    if (preg_match('/over\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m) || preg_match('/above\s+(\d+(?:\.\d+)?)/i', $rawMessage, $m)) $out["min_price"] = (float) $m[1];
    // Turkish budget patterns: "500 TL altı", "500 altı", "500'den ucuz"
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(tl|₺)?\s*(alt[ıi]|altinda|altında|alti)/ui', $rawMessage, $m)) $out["max_price"] = (float) str_replace(',', '.', $m[1]);
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(tl|₺)?\s*(ust[üu]|üstü|ustu|uzeri|üzeri)/ui', $rawMessage, $m)) $out["min_price"] = (float) str_replace(',', '.', $m[1]);
    if (preg_match('/\b(tl|₺|try)\b/ui', $rawMessage)) $out["budget"]["currency"] = "TRY";
    if (preg_match('/\b(usd|dollar|\$)\b/ui', $rawMessage)) $out["budget"]["currency"] = "USD";
    $out["budget"]["min"] = $out["min_price"];
    $out["budget"]["max"] = $out["max_price"];
    if (preg_match('/\b(black|white|red|blue|green|gray|grey|brown|beige)\b/i', $rawMessage, $m)) $out["color"] = strtolower($m[1]);
    if (preg_match('/\b(siyah|beyaz|kırmızı|kirmizi|mavi|yeşil|yesil|gri|kahverengi|bej)\b/ui', $rawMessage, $m)) $out["color"] = to_lower($m[1]);
    if (preg_match('/\b(?:size|numara)\s*(\d{1,2}(?:\.\d+)?)\b/i', $rawMessage, $m)) $out["size"] = $m[1];
    if (preg_match('/\bbrand\s+([a-z0-9\-]+)/i', $rawMessage, $m)) $out["brand"] = strtolower($m[1]);
    if (preg_match('/\b(cheapest|lowest|low to high|en ucuz|ucuz)\b/i', $rawMessage)) $out["sort_by"] = "price_asc";
    elseif (preg_match('/\b(expensive|high to low|premium|pahalı)\b/i', $rawMessage)) $out["sort_by"] = "price_desc";
    elseif (preg_match('/\b(newest|latest|yeni)\b/i', $rawMessage)) $out["sort_by"] = "newest";

    // Structured feature extraction
    if (preg_match('/\b(kablosuz|bluetooth|wireless)\b/ui', $text)) {
        $out["features"][] = "wireless";
        $out["keywords"][] = "wireless";
    }
    if (preg_match('/\b(gaming|oyuncu)\b/ui', $text)) $out["features"][] = "gaming";
    if (preg_match('/\b(noise ?cancell|gürültü ?engelle|gurultu ?engelle|anc)\b/ui', $text)) $out["features"][] = "noise_cancelling";
    if (preg_match('/\b(microphone|mic|mikrofon)\b/ui', $text)) $out["features"][] = "microphone";

    // Query rewrite for common shopping synonyms
    if (preg_match('/\b(kulaklık|kulaklik|headset|headphone|headphones|earbuds)\b/ui', $text)) {
        $out["product_type"] = "headphone";
        $out["keywords"][] = "headphone";
    }

    $categoryMap = ["running"=>"running","koşu"=>"running","shoe"=>"shoe","shoes"=>"shoe","ayakkabı"=>"shoe","ayakkabi"=>"shoe","sneaker"=>"sneaker","sneakers"=>"sneaker","electronics"=>"electronics","phone"=>"phone","telefon"=>"phone","headphone"=>"headphone","headphones"=>"headphone","earbuds"=>"headphone","wireless"=>"wireless","women"=>"women","kadın"=>"women","kadin"=>"women","men"=>"men","erkek"=>"men"];
    foreach ($categoryMap as $token => $value) {
        if (str_contains($text, $token)) {
            $out["category_like"] = $value;
            if ($out["product_type"] === null) $out["product_type"] = $value;
            break;
        }
    }

    if (preg_match('/\b(men|male|erkek)\b/ui', $text)) {
        $out["audience"] = "men";
        $out["category_like"] = "men";
    } elseif (preg_match('/\b(women|female|kadın|kadin)\b/ui', $text)) {
        $out["audience"] = "women";
        $out["category_like"] = "women";
    }

    $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text)) ?: [];
    $stop = ["i","need","under","over","show","me","for","the","a","an","to","my","ve","ile","olan","olanları","göster","goster","bana"];
    foreach ($words as $w) {
        $w = trim($w);
        if ($w === "" || strlen($w) < 3 || in_array($w, $stop, true) || is_numeric($w)) continue;
        $out["keywords"][] = $w;
    }
    $out["keywords"] = array_values(array_unique($out["keywords"]));
    $out["features"] = array_values(array_unique($out["features"]));
    if (empty($out["brand"])) {
        foreach (["nike","adidas","puma","reebok","new balance","asics","converse"] as $b) {
            if (str_contains($text, $b)) { $out["brand"] = $b; break; }
        }
    }
    return $out;
}


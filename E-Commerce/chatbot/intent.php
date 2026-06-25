<?php

require_once __DIR__ . '/tr_synonyms.php';

function message_mentions_product_terms(string $message): bool
{
    return (bool) (
        preg_match('/\b(etek|elbise|tişört\w*|tisort\w*|tişör\w*|ayakkabı\w*|ayakkabi\w*|gömlek\w*|gomlek\w*|pantolon\w*|çanta|canta|kulaklık\w*|kulaklik\w*|tencere|mont|takı|taki|bisiklet|bluz|vitamin|koşu|kosu|mutfak|beden\w*|malzeme|ceket|saat|parfüm|parfum|telefon|laptop|hediye|şarj|sarj|yazıcı|yazici|kamera|kitap)\b/ui', $message)
        || preg_match('/\b(dresses?|skirts?|shoes?|sneakers?|headphones?|laptops?|shirts?|tshirts?|jackets?|pants|jeans)\b/ui', $message)
    );
}

function is_order_tracking_context(string $message): bool
{
    return (bool) preg_match(
        '/\b(?:'
        . 'order\s*(?:#|no\.?|num|number|id)\s*\d+'
        . '|my\s+order'
        . '|order\s+status'
        . '|track(?:ing)?\s+(?:my\s+)?order'
        . '|where\s+is\s+(?:my\s+)?order'
        . '|latest\s+order'
        . '|recent\s+orders?'
        . '|sipari[sş](?:im|imde|lerim)?\s*(?:nerede|durum|takip)'
        . '|sipari[sş]\s*(?:#|no|numara)?\s*\d+'
        . '|sipari[sş]\s+durumu'
        . ')\b/ui',
        $message
    );
}

function is_order_purchase_intent(string $message): bool
{
    if (!message_mentions_product_terms($message)) {
        return false;
    }
    if (is_order_tracking_context($message)) {
        return false;
    }
    if (preg_match('/\b(?:want\s+to\s+)?order\s+(?:me\s+)?(?:some\s+|a\s+)?/ui', $message)) {
        return true;
    }
    if (preg_match('/\b(?:sipari[sş]|siparis)\s+ver\b/ui', $message)) {
        return true;
    }
    if (preg_match('/\b(?:order|sipari[sş]|siparis)\s+(?!#|\d|no\b|numara\b|status|durum|takip|nerede|ver\b)/ui', $message)) {
        return true;
    }
    return false;
}

function is_order_tracking_intent(string $message): bool
{
    if (is_order_purchase_intent($message)) {
        return false;
    }
    return (bool) preg_match('/\b(order\w*|package\w*|shipment\w*|paket\w*|sipariş\w*|siparis\w*)\b/ui', $message);
}

function is_audience_correction_message(string $message): bool
{
    return (bool) preg_match(
        '/\b(?:'
        . '(?:kadın|kadin|bayan|women|female|erkek|men|male)\s+(?:dedim|istedim|istiyorum|demiştim|demistim|söyledim|soyledim)'
        . '|(?:ben\s+)?(?:kadın|kadin|bayan|women|erkek|men)\s+(?:istiyorum|arıyorum|ariyorum)'
        . '|i\s+said\s+(?:women|woman|men|male|female)'
        . '|(?:women|men)(?:\'s)?\s+(?:only|please)'
        . ')\b/ui',
        $message
    );
}

function is_best_sellers_request(string $message): bool
{
    return (bool) preg_match(
        '/\b(?:'
        . 'best\s*sellers?'
        . '|best[\s-]*selling'
        . '|top\s*sellers?'
        . '|most\s*(?:popular|sold)'
        . '|en\s*çok\s*satan(?:lar|ları)?'
        . '|cok\s*satan(?:lar|ları)?'
        . '|çok\s*satan(?:lar|ları)?'
        . '|popüler\s*ürün(?:ler)?'
        . '|populer\s*urun(?:ler)?'
        . ')\b/ui',
        $message
    );
}

function is_generic_product_recommendation_request(string $message, string $quickAction = ''): bool
{
    if ($quickAction === 'recommend_product') {
        return true;
    }

    $lower = to_lower(trim($message));
    $normalized = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $lower);
    $normalized = trim((string) preg_replace('/\s+/u', ' ', $normalized));

    $exactPhrases = [
        'bana ürün öner',
        'ürün öner',
        'bana bir ürün öner',
        'ürün önerir misin',
        'recommend me a product',
        'recommend a product',
        'suggest me a product',
        'suggest a product',
    ];
    if (in_array($normalized, $exactPhrases, true)) {
        return true;
    }

    if (message_specifies_budget($message)) {
        return false;
    }
    if (message_mentions_product_terms($message)) {
        return false;
    }

    return (bool) preg_match(
        '/^(?:'
        . 'bana\s+(?:bir\s+)?ürün\s+öner'
        . '|ürün\s+öner(?:ir\s+misin)?'
        . '|recommend(?:\s+me)?(?:\s+a)?\s+product'
        . '|suggest(?:\s+me)?(?:\s+a)?\s+product'
        . ')$/ui',
        $normalized
    );
}

function message_specifies_budget(string $message): bool
{
    if (!function_exists('parse_shopping_budget')) {
        return (bool) preg_match('/\d/', $message)
            && preg_match('/\b(alt[ıi]|altinda|altında|under|below|€|\$|eur|euro|tl|try)\b/ui', $message);
    }
    $parsed = parse_shopping_budget($message);
    return $parsed['max_price_usd'] !== null || $parsed['min_price_usd'] !== null;
}

/**
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function reset_entities_for_generic_recommendation(array $entities): array
{
    $entities['_generic_recommend'] = true;
    $entities['max_price'] = null;
    $entities['min_price'] = null;
    $entities['budget'] = ['min' => null, 'max' => null, 'currency' => 'USD'];
    $entities['category_like'] = null;
    $entities['product_type'] = null;
    $entities['keywords'] = [];
    $entities['sort_by'] = 'featured_price';
    unset($entities['_cheaper_refinement'], $entities['_best_sellers_request']);
    return $entities;
}

function detect_intent(string $message): string
{
    if (preg_match('/\b((?:xxs|xs|s|m|l|xl|xxl|2xl|3xl)\s+beden|beden(?:i)?\s+(?:xxs|xs|s|m|l|xl|xxl|2xl|3xl)|sizes?|beden|numara)\b.*\b(var\s+m[ıi]|available|mevcut)\b/ui', $message)
        || preg_match('/\b(var\s+m[ıi]|available|mevcut)\b.*\b(beden|beden|sizes?|numara)\b/ui', $message)
        || preg_match('/\b(stock|stok|stokta)\b/ui', $message)) {
        return "general";
    }

    if (preg_match('/\b(return\w*|refund\w*|money\s+back|[iİ]ade\w*)\b/ui', $message)) return "returns";
    if (preg_match('/\b(payment|pay\w*|card|credit|debit|ödeme\w*|odeme\w*|kart\w*|taksit\w*)\b/ui', $message)) return "payment";
    if (preg_match('/\b(shipping|cargo|delivery|kargo\w*|teslim\w*|gönderi\w*|gonderi\w*)\b/ui', $message)) return "shipping";
    if (is_order_purchase_intent($message)) return "product_search";
    if (is_order_tracking_intent($message)) return "order_status";
    if (preg_match('/\b(cart|basket|sepet\w*)\b/ui', $message)) return "cart_question";
    if (is_best_sellers_request($message)) return "product_search";
    if (preg_match('/\b(recommend|suggest|need|looking\s+for|find|show|öner|oner|göster|goster|bul|ara|almak\s+istiyorum|satın\s+al|satin\s+al|arıyorum|ariyorum|bakıyorum|bakiyorum)\b/ui', $message)) {
        return "product_search";
    }
    if (preg_match('/\b(etek|elbise|tişört\w*|tisort\w*|tişör\w*|ayakkabı\w*|ayakkabi\w*|gömlek\w*|gomlek\w*|pantolon\w*|çanta|canta|kulaklık\w*|kulaklik\w*|tencere|mont|takı|taki|bisiklet|bluz|vitamin|koşu|kosu|mutfak|beden\w*|malzeme|ceket|saat|parfüm|parfum|telefon|laptop|hediye|şarj|sarj|yazıcı|yazici|kamera|kitap)\b/ui', $message)) {
        return "product_search";
    }
    if (preg_match('/\b(dresses?|skirts?|shoes?|sneakers?|headphones?|laptops?|shirts?|tshirts?|jackets?|pants|jeans)\b/ui', $message)) {
        return "product_search";
    }
    if (preg_match('/\d/', $message) && preg_match('/\b(alt[ıi]|altinda|altında|under|below|üstü|ustu|over|above)\b/ui', $message)) {
        return "product_search";
    }
    return "general";
}

function detect_language(string $rawMessage): string
{
    if (preg_match('/[çğıöşü]/iu', $rawMessage)) return "tr";
    if (preg_match('/\b(merhaba|selam|kargo\w*|iade\w*|sipariş\w*|siparis\w*|sepet\w*|teşekkür|tesekkur|öner|oner|ayakkabı|ayakkabi|tişört|tisort|mutfak|elbise|telefon|kulaklık|kulaklik|gömlek|gomlek|pantolon|hediye|ucuz|beden|stok)\b/iu', to_lower($rawMessage))) {
        return "tr";
    }
    return "en";
}

function detect_policy_lock_intent(string $message): ?string
{
    if (preg_match('/\b(return\w*|refund\w*|money\s+back|[iİ]ade\w*)\b/ui', $message)) return "returns";
    if (preg_match('/\b(payment|pay\w*|card|credit|debit|ödeme\w*|odeme\w*|kart\w*)\b/ui', $message)) return "payment";
    if (is_order_tracking_intent($message)) return "order_status";
    if (preg_match('/\b(shipping|cargo|delivery|kargo\w*|teslim\w*|gönderi\w*|gonderi\w*)\b/ui', $message)) return "shipping";
    return null;
}

function extract_entities(string $rawMessage): array
{
    $rawMessage = normalize_turkish_shopping_query($rawMessage);
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
        "keywords" => [],
    ];

    $parsedBudget = parse_shopping_budget($rawMessage);
    if ($parsedBudget['max_price_usd'] !== null) {
        $out['max_price'] = $parsedBudget['max_price_usd'];
        $out['budget']['max'] = $parsedBudget['max_amount'];
        $out['budget']['max_usd'] = $parsedBudget['max_price_usd'];
        $out['budget']['currency'] = $parsedBudget['currency'];
        $out['sort_by'] = 'price_asc';
    }
    if ($parsedBudget['min_price_usd'] !== null) {
        $out['min_price'] = $parsedBudget['min_price_usd'];
        $out['budget']['min'] = $parsedBudget['min_amount'];
        $out['budget']['min_usd'] = $parsedBudget['min_price_usd'];
        $out['budget']['currency'] = $parsedBudget['currency'];
    }
    if ($parsedBudget['max_amount'] === null && $parsedBudget['min_amount'] === null) {
        $out['budget']['currency'] = $parsedBudget['currency'];
    }
    $out['budget']['min'] = $out['min_price'];
    $out['budget']['max'] = $parsedBudget['max_amount'] ?? $out['budget']['max'];

    $colorMap = [
        'black' => 'black', 'white' => 'white', 'red' => 'red', 'blue' => 'blue', 'green' => 'green',
        'gray' => 'gray', 'grey' => 'grey', 'brown' => 'brown', 'beige' => 'beige',
        'siyah' => 'siyah', 'beyaz' => 'beyaz', 'kırmızı' => 'kırmızı', 'kirmizi' => 'kırmızı',
        'mavi' => 'mavi', 'yeşil' => 'yeşil', 'yesil' => 'yeşil', 'gri' => 'gri',
        'kahverengi' => 'kahverengi', 'bej' => 'bej', 'sarı' => 'sarı', 'sari' => 'sarı',
        'turuncu' => 'turuncu', 'mor' => 'mor', 'pembe' => 'pembe', 'lacivert' => 'lacivert',
    ];
    foreach ($colorMap as $token => $value) {
        if (preg_match('/\b' . preg_quote($token, '/') . '\b/ui', $rawMessage)) {
            $out["color"] = $value;
            break;
        }
    }

    if (preg_match('/\b(?:size|numara)\s*(\d{1,2}(?:\.\d+)?)\b/i', $rawMessage, $m)) {
        $out["size"] = $m[1];
    }
    if (preg_match('/\b(?:size|beden|numara)\s+(?!(?:are|is|available|var|mevcut)\b)(xxs|xs|s|m|l|xl|xxl|2xl|3xl)\b/ui', $rawMessage, $m)) {
        $out["size"] = strtoupper($m[1]);
    }
    if (preg_match('/\bbeden(?:i)?\s+(xxs|xs|s|m|l|xl|xxl|2xl|3xl)\s+olan\b/ui', $rawMessage, $m)) {
        $out["size"] = strtoupper($m[1]);
    }
    if (preg_match('/\b(?:have|has|var)\b.*\b(?:size|beden)\s+(xxs|xs|s|m|l|xl|xxl|2xl|3xl)\b/ui', $rawMessage, $m)) {
        $out["size"] = strtoupper($m[1]);
    }
    if (preg_match('/\bbrand\s+([a-z0-9\-]+)/i', $rawMessage, $m)) {
        $out["brand"] = strtolower($m[1]);
    }

    if (preg_match('/\b(cheapest|lowest|low\s+to\s+high|en\s+ucuz|ucuz)\b/i', $rawMessage)) {
        $out["sort_by"] = "price_asc";
    } elseif (preg_match('/\b(expensive|high\s+to\s+low|premium|pahalı|pahali)\b/i', $rawMessage)) {
        $out["sort_by"] = "price_desc";
    } elseif (preg_match('/\b(newest|latest|yeni)\b/i', $rawMessage)) {
        $out["sort_by"] = "newest";
    }

    $out = enrich_entities_from_turkish($text, $out);

    if (preg_match('/\b(dress|dresses|gown|sundress)\b/ui', $rawMessage)) {
        $out['product_type'] = 'dress';
        $out['category_like'] = 'dress';
    } elseif (preg_match('/\b(skirt|skirts)\b/ui', $rawMessage)) {
        $out['product_type'] = 'skirts';
        $out['category_like'] = 'skirts';
    } elseif (preg_match('/\b(tshirt|t-shirt|tshirts|shirts?)\b/ui', $rawMessage)) {
        $out['product_type'] = 'shirt';
        $out['category_like'] = 'shirt';
    } elseif (preg_match('/\b(sneakers?|shoes?|boots?)\b/ui', $rawMessage) && empty($out['product_type'])) {
        $out['product_type'] = 'shoe';
        $out['category_like'] = 'shoe';
    } elseif (preg_match('/\b(headphones?|earbuds?)\b/ui', $rawMessage) && empty($out['product_type'])) {
        $out['product_type'] = 'headphone';
        $out['category_like'] = 'headphone';
    } elseif (preg_match('/\b(jackets?|coats?|hoodies?)\b/ui', $rawMessage) && empty($out['product_type'])) {
        $out['product_type'] = 'jacket';
        $out['category_like'] = 'jacket';
    }

    $words = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text)) ?: [];
    $stop = turkish_search_stop_words();
    foreach ($words as $w) {
        $w = trim($w);
        if ($w === "" || mb_strlen($w) < 3 || in_array($w, $stop, true) || is_numeric($w)) {
            continue;
        }
        $out["keywords"][] = $w;
    }

    $out["keywords"] = array_values(array_unique($out["keywords"]));
    $out["features"] = array_values(array_unique($out["features"]));

    if (empty($out["brand"])) {
        foreach (["nike", "adidas", "puma", "reebok", "new balance", "asics", "converse", "apple", "samsung", "huawei", "xiaomi", "rolex"] as $b) {
            if (str_contains($text, $b)) {
                $out["brand"] = $b;
                break;
            }
        }
    }

    if (is_best_sellers_request($rawMessage)) {
        $out["sort_by"] = "best_sellers";
        $out["_best_sellers_request"] = true;
        $out["keywords"] = [];
        $out["category_like"] = null;
        $out["product_type"] = null;
    }

    return $out;
}

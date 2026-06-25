<?php

function to_lower(string $text): string
{
    return function_exists("mb_strtolower") ? mb_strtolower($text, "UTF-8") : strtolower($text);
}

function pick_deterministic_reply(array $options, string $seed): string
{
    if ($options === []) {
        return '';
    }
    $idx = abs((int) crc32($seed)) % count($options);
    return $options[$idx];
}

function get_chat_user_name(): string
{
    $raw = trim((string) ($_SESSION["user_name"] ?? ""));
    if ($raw === "") return "";
    // keep it short/safe for chat responses
    $clean = preg_replace('/[^\p{L}\p{N}\s\-\._]/u', '', $raw);
    $clean = trim((string) $clean);
    return mb_substr($clean, 0, 32);
}

/**
 * Quick-action chip codes (ASCII) — POST gövdesi hosting tarafından silinirse kullanılır.
 */
function resolve_quick_action_message(string $action, string $lang = 'en'): string
{
    $action = strtolower(trim($action));
    $map = [
        'recommend_product' => ['en' => 'Recommend me a product', 'tr' => 'Bana ürün öner'],
        'track_order' => ['en' => 'Track my order', 'tr' => 'Siparişimi takip et'],
        'payment_options' => ['en' => 'Payment options', 'tr' => 'Ödeme seçenekleri'],
        'returns' => ['en' => 'How do returns work?', 'tr' => 'İade nasıl yapılır?'],
        'wireless_only' => ['en' => 'Wireless only', 'tr' => 'Kablosuz olsun'],
        'cheaper_alternatives' => ['en' => 'Cheaper alternatives', 'tr' => 'Daha ucuz alternatif'],
        'best_sellers' => ['en' => 'Best sellers', 'tr' => 'En çok satanlar'],
        'under_100' => ['en' => 'Under €100', 'tr' => '100 euro altı'],
        'shipment_where' => ['en' => 'Where is my shipment?', 'tr' => 'Kargo nerede?'],
        'delivery_time' => ['en' => 'Delivery time', 'tr' => 'Teslimat süresi'],
        'cancel_order' => ['en' => 'Cancel my order', 'tr' => 'Siparişi iptal et'],
        'installments' => ['en' => 'Do you offer installments?', 'tr' => 'Taksit yapılıyor mu?'],
        'shipping_time' => ['en' => 'Shipping time', 'tr' => 'Kargo süresi'],
    ];
    if (!isset($map[$action])) {
        return '';
    }
    $lang = in_array($lang, ['en', 'tr'], true) ? $lang : 'en';
    return $map[$action][$lang] ?? $map[$action]['en'] ?? '';
}


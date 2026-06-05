<?php

/**
 * Yanıt ile önerilen ürünlerin sorgu entity'leriyle uyumunu doğrular.
 */

function product_violates_entity_constraints(array $product, array $entities): ?string
{
    $name = function_exists('to_lower') ? to_lower((string) ($product['name'] ?? '')) : strtolower((string) ($product['name'] ?? ''));
    $sub = function_exists('to_lower') ? to_lower((string) ($product['sub_category'] ?? '')) : strtolower((string) ($product['sub_category'] ?? ''));
    $cat = function_exists('to_lower') ? to_lower((string) ($product['category'] ?? '')) : strtolower((string) ($product['category'] ?? ''));
    $price = (float) ($product['price'] ?? 0);

    if (is_numeric($entities['max_price'] ?? null) && $price > (float) $entities['max_price'] + 0.01) {
        return 'price above max_price';
    }
    if (is_numeric($entities['min_price'] ?? null) && $price < (float) $entities['min_price'] - 0.01) {
        return 'price below min_price';
    }

    $type = function_exists('to_lower')
        ? to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''))
        : strtolower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));

    if ($type === 'shirt') {
        $shirtLike = str_contains($name, 'shirt') || str_contains($name, 'tshirt') || str_contains($name, 't-shirt')
            || str_contains($sub, 'shirt');
        $wrongAccessory = preg_match('/\b(bag|bracelet|clutch|wallet|necklace|ring|earring|pendant|bangle|tote|satchel)\b/u', $name);
        if ($wrongAccessory || (!$shirtLike && !str_contains($sub, 'shirt'))) {
            return 'not a shirt';
        }
        if (($entities['audience'] ?? null) === 'men' && (str_contains($name, 'women') || str_contains($name, 'womens'))) {
            return 'women product for men audience';
        }
        if (($entities['audience'] ?? null) === 'women' && str_contains($name, 'men') && !str_contains($name, 'women')) {
            return 'men product for women audience';
        }
    }

    if ($type === 'kitchen' || (function_exists('is_turkish_footwear_search') && !is_turkish_footwear_search($entities) && $type === 'kitchen')) {
        if (preg_match('/\b(cleat|sneaker|loafer|jordan|boot|sandal|trainer|ayakkab)\b/u', $name)) {
            return 'footwear in kitchen search';
        }
    }

    if (function_exists('is_footwear_product_search') && is_footwear_product_search($entities)) {
        if (!preg_match('/\b(shoes?|sneakers?|trainers?|boots?|sandals?|cleats?|jordan|slippers?|loafers?|ayakkab)\b/u', $name)
            && !preg_match('/\b(shoes?|sneakers?|trainers?|boots?|sandals?|cleats?|jordan|slippers?|loafers?)\b/u', $sub)) {
            return 'not footwear';
        }
    }

    if (in_array($type, ['dress', 'skirts'], true)) {
        $match = preg_match('/\b(dress|dresses|skirt|skirts|gown|maxi|midi)\b/u', $name . ' ' . $sub . ' ' . $cat);
        if (!$match) {
            return 'not dress/skirt';
        }
    }

    if (in_array($type, ['fiction', 'non-fiction'], true)) {
        if (!preg_match('/\b(book|books|fiction|novel|textbook|roman)\b/u', $name . ' ' . $sub)) {
            return 'not a book';
        }
    }

    if ($type === 'camera') {
        if (!preg_match('/\b(camera|cameras|dslr|mirrorless|lens|webcam)\b/u', $name . ' ' . $sub)) {
            return 'not a camera';
        }
    }

    if ($type === 'jacket') {
        if (!preg_match('/\b(jacket|coat|hoodie|blazer|parka|bomber|mont)\b/u', $name . ' ' . $sub)) {
            return 'not outerwear';
        }
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $products
 * @return array<int, array<string, mixed>>
 */
function filter_products_for_entities(array $products, array $entities): array
{
    $out = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        if (product_violates_entity_constraints($product, $entities) === null) {
            $out[] = $product;
        }
    }
    return $out;
}

function reply_has_product_list(string $reply): bool
{
    return (bool) preg_match('/^\s*-\s.+\(\$\d+/m', $reply);
}

function reply_claims_no_match(string $reply, string $lang): bool
{
    $lower = function_exists('to_lower') ? to_lower($reply) : strtolower($reply);
    if ($lang === 'tr') {
        return str_contains($lower, 'bulamadım') || str_contains($lower, 'tam eşleşme bulamadım');
    }
    return str_contains($lower, "couldn't find") || str_contains($lower, 'no matches');
}

function is_intent_reply_mismatch(string $intent, string $reply, array $products): bool
{
    if (in_array($intent, ['shipping', 'returns', 'payment', 'order_status'], true)) {
        if (!empty($products) || reply_has_product_list($reply)) {
            return true;
        }
        $lower = function_exists('to_lower') ? to_lower($reply) : strtolower($reply);
        if (preg_match('/\b(recommend|suggest|öner|göz at|great deal|ürün öner|şunlara göz at)\b/ui', $lower)) {
            return true;
        }
    }

    if ($intent === 'product_search') {
        if (reply_claims_no_match($reply, 'tr') || reply_claims_no_match($reply, 'en')) {
            if (reply_has_product_list($reply) && !str_contains($reply, 'En yakın') && !str_contains($reply, 'Closest option')) {
                return true;
            }
        }
        if ($products === [] && reply_has_product_list($reply)
            && !str_contains($reply, 'En yakın') && !str_contains($reply, 'Closest option')) {
            return true;
        }
    }

    return false;
}

function should_skip_clarification(string $intent, array $entities, string $reply): bool
{
    if ($intent === 'product_search') {
        if (is_numeric($entities['max_price'] ?? null) || is_numeric($entities['min_price'] ?? null)) {
            return true;
        }
        if (function_exists('has_specific_product_query') && has_specific_product_query($entities)) {
            return true;
        }
    }
    if (in_array($intent, ['shipping', 'returns', 'payment', 'order_status'], true) && strlen(trim($reply)) >= 36) {
        return true;
    }
    if ($intent === 'product_followup' && strlen(trim($reply)) >= 20) {
        return true;
    }
    return false;
}

/**
 * Yanıt ve önerilen ürünleri tutarlı hale getirir; tutarsızsa rule-based yanıtı yeniden üretir.
 *
 * @return array{0:string,1:array,2:string}
 */
function enforce_response_consistency(
    PDO $pdo,
    string $intent,
    string $reply,
    array $products,
    array $entities,
    string $lang,
    string $source
): array {
    if ($intent === 'product_search') {
        $products = filter_products_for_entities($products, $entities);

        if ($products === [] && reply_has_product_list($reply)
            && !str_contains($reply, 'En yakın') && !str_contains($reply, 'Closest option')) {
            if (is_numeric($entities['max_price'] ?? null)) {
                $reply = build_product_reply([], $entities, $lang);
                $closest = search_closest_above_budget($pdo, $entities, 1);
                $closest = filter_products_for_entities($closest, $entities);
                if ($closest !== []) {
                    $near = $closest[0];
                    $nearPrice = number_format((float) ($near['price'] ?? 0), 2);
                    $nearName = (string) ($near['name'] ?? 'Product');
                    $reply .= $lang === 'tr'
                        ? "\n\nEn yakın seçenek: {$nearName} (\${$nearPrice})"
                        : "\n\nClosest option: {$nearName} (\${$nearPrice})";
                }
            } elseif (function_exists('has_specific_product_query') && has_specific_product_query($entities)) {
                $reply = build_product_reply([], $entities, $lang);
            } else {
                $reply = $lang === 'tr'
                    ? 'Tam eşleşme bulamadım. Bütçe veya ürün tipi yazar mısın? (örn: 100 euro altı koşu ayakkabısı)'
                    : "I couldn't find matches. Try adding a budget or product type (for example: running shoes under €100).";
            }
            $source = 'product_search';
        } elseif ($products !== [] && !reply_has_product_list($reply)) {
            $reply = build_product_reply($products, $entities, $lang);
            $source = 'product_search';
        }
    }

    if (in_array($intent, ['shipping', 'returns', 'payment', 'order_status'], true)) {
        $products = [];
        if (is_intent_reply_mismatch($intent, $reply, $products)) {
            $policyKnowledge = load_policy_knowledge();
            if ($intent === 'shipping') {
                $reply = build_shipping_reply((string) ($entities['_raw_message'] ?? ''), $policyKnowledge, $lang);
            } elseif ($intent === 'returns') {
                $reply = build_returns_reply((string) ($entities['_raw_message'] ?? ''), $policyKnowledge, $lang);
            } elseif ($intent === 'payment') {
                $reply = build_payment_reply((string) ($entities['_raw_message'] ?? ''), $policyKnowledge, $lang);
            }
            $source = 'rule_based';
        }
    }

    if (is_intent_reply_mismatch($intent, $reply, $products)) {
        if ($intent === 'product_search' && $products !== []) {
            $reply = build_product_reply($products, $entities, $lang);
            $source = 'product_search';
        }
    }

    return [$reply, $products, $source];
}

function policy_intent_has_product_leak(string $intent, string $reply, array $products, string $source): bool
{
    if (!in_array($intent, ['shipping', 'returns', 'payment', 'order_status'], true)) {
        return false;
    }
    return $source === 'product_search'
        || !empty($products)
        || reply_has_product_list($reply)
        || str_contains(function_exists('to_lower') ? to_lower($reply) : strtolower($reply), 'here are some good options')
        || str_contains(function_exists('to_lower') ? to_lower($reply) : strtolower($reply), 'sana uygun olabilecek')
        || str_contains(function_exists('to_lower') ? to_lower($reply) : strtolower($reply), 'şunlara göz atabilirsin');
}

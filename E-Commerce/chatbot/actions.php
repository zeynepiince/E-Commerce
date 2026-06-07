<?php

require_once __DIR__ . '/tr_synonyms.php';
require_once __DIR__ . '/consistency.php';

function is_footwear_product_search(array $entities): bool
{
    return is_turkish_footwear_search($entities);
}

/**
 * Türkçe anahtar kelimeleri DB arama terimlerine genişletir.
 *
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function expand_entities_for_product_search(array $entities): array
{
    $expanded = expand_turkish_search_keywords(
        filter_product_search_keywords(is_array($entities['keywords'] ?? null) ? $entities['keywords'] : []),
        !empty($entities['category_like']) ? (string) $entities['category_like'] : null,
        !empty($entities['product_type']) ? (string) $entities['product_type'] : null
    );
    $entities['keywords'] = $expanded;
    return $entities;
}

function append_clothing_type_guard(string &$sql, array &$params, array $entities): void
{
    $type = to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));
    if ($type === 'shirt') {
        $sql .= " AND (
            LOWER(p.sub_category) LIKE '%shirt%'
            OR LOWER(p.name) LIKE '%shirt%'
            OR LOWER(p.name) LIKE '%tshirt%'
            OR LOWER(p.name) LIKE '%t-shirt%'
        )";
        if (($entities['audience'] ?? null) === 'women') {
            $sql .= " AND (
                LOWER(p.name) LIKE '%women%'
                OR LOWER(p.name) LIKE '%womens%'
                OR LOWER(c.category_name) IN ('women''s clothing', 'womens clothing')
            )";
        } elseif (($entities['audience'] ?? null) === 'men') {
            $sql .= " AND (
                LOWER(p.name) LIKE '%men%'
                OR LOWER(c.category_name) IN ('men''s clothing', 'mens clothing')
            )
            AND LOWER(p.name) NOT LIKE '%women%'
            AND LOWER(p.name) NOT LIKE '%womens%'";
        }
    }
}

function append_footwear_name_guard(string &$sql, array &$params, string $mode = 'default'): void
{
    if ($mode === 'budget') {
        $sql .= " AND LOWER(p.sub_category) IN ('men-shoes', 'running')";
        $sql .= " AND LOWER(p.name) NOT LIKE '%slipper%'";
        $sql .= " AND LOWER(p.name) NOT LIKE '%strainer%'";
        return;
    }

    if ($mode === 'cheapest' || $mode === 'brand') {
        $sql .= " AND (
            LOWER(p.sub_category) IN ('men-shoes', 'women-shoes', 'running')
            OR LOWER(p.name) REGEXP 'sneaker|trainer|cleat|jordan|nike|adidas|puma|reebok|running|calvin'
        )";
        $sql .= " AND LOWER(p.name) NOT LIKE '%slipper%'";
        $sql .= " AND LOWER(p.name) NOT LIKE '%terlik%'";
        $sql .= " AND LOWER(p.name) NOT LIKE '%strainer%'";
        return;
    }

    $patterns = ["%sneaker%", "%trainer%", "%boot%", "%sandal%", "%cleat%", "%jordan%", "%loafer%", "%ayakkab%", "%shoe%"];
    $parts = [];
    foreach ($patterns as $pattern) {
        $parts[] = "LOWER(p.name) LIKE ?";
        $parts[] = "LOWER(p.description) LIKE ?";
        $params[] = $pattern;
        $params[] = $pattern;
    }
    $sql .= " AND (" . implode(" OR ", $parts) . ")";
    $sql .= " AND LOWER(p.name) NOT LIKE '%slipper%'";
    $sql .= " AND LOWER(p.name) NOT LIKE '%terlik%'";
    $sql .= " AND LOWER(p.name) NOT LIKE '%strainer%'";
}

function should_use_athletic_footwear_guard(array $entities, string $rawMessage): string
{
    if (!is_footwear_product_search($entities)) {
        return 'default';
    }
    if (preg_match('/\b(slipper|slippers|terlik)\b/ui', $rawMessage)) {
        return 'default';
    }
    if (is_numeric($entities['max_price'] ?? null) || is_numeric($entities['min_price'] ?? null)) {
        return 'budget';
    }
    if (preg_match('/\b(en\s+ucuz|cheapest|lowest)\b/ui', $rawMessage)) {
        return 'cheapest';
    }
    if (!empty($entities['brand'])) {
        return 'brand';
    }
    return 'default';
}

function append_skirts_dress_guard(string &$sql, array &$params, string $clothingType): void
{
    if ($clothingType === 'skirts') {
        $sql .= " AND (
            LOWER(p.name) LIKE '%skirt%'
            OR LOWER(p.sub_category) LIKE '%skirt%'
            OR LOWER(p.name) LIKE '%etek%'
        )";
        return;
    }
    if ($clothingType === 'dress') {
        $sql .= " AND (
            LOWER(p.name) LIKE '%dress%'
            OR LOWER(p.sub_category) LIKE '%dress%'
            OR LOWER(p.name) LIKE '%frock%'
            OR LOWER(p.name) LIKE '%gown%'
        )";
    }
}

function is_cheaper_refinement_message(string $rawMessage): bool
{
    return (bool) preg_match(
        '/\b(cheaper|more affordable|affordable|daha ucuz|biraz daha ucuz|ucuz(?:\s+alternatif\w*)?|cheaper\s+alternatives?|affordable\s+alternatives?)\b/ui',
        $rawMessage
    );
}

function is_wireless_refinement_message(string $rawMessage): bool
{
    if (preg_match(
        '/\b((?:only|sadece)\s+(?:wireless|kablosuz|bluetooth)|(?:wireless|kablosuz|bluetooth)\s+(?:only|olsun)|kablosuz\s+olsun|wireless\s+options?|show\s+wireless)\b/ui',
        $rawMessage
    )) {
        return true;
    }

    return preg_match('/\b(kablosuz|wireless|bluetooth)\b/ui', $rawMessage)
        && preg_match('/\b(only|sadece|olsun)\b/ui', $rawMessage);
}

/** @return array<int, string> */
function wireless_refinement_noise_keywords(): array
{
    return array_merge(refinement_noise_keywords(), [
        'wireless', 'kablosuz', 'bluetooth', 'only', 'sadece', 'olsun', 'just', 'show', 'options', 'option',
    ]);
}

/**
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function strip_wireless_refinement_noise_from_entities(array $entities): array
{
    $noise = wireless_refinement_noise_keywords();
    if (!empty($entities['keywords']) && is_array($entities['keywords'])) {
        $entities['keywords'] = array_values(array_filter($entities['keywords'], static function ($kw) use ($noise): bool {
            $lower = to_lower(trim((string) $kw));
            return $lower !== '' && !in_array($lower, $noise, true);
        }));
    } else {
        $entities['keywords'] = [];
    }

    return $entities;
}

/** @return array<int, string> */
function refinement_noise_keywords(): array
{
    return [
        'product', 'products', 'alternatives', 'alternative', 'alternatif', 'alternatifler',
        'cheaper', 'affordable', 'recommend', 'recommendation', 'option', 'options',
        'ürün', 'urun', 'öner', 'oner', 'suggest', 'suggestion', 'looking', 'help', 'me',
    ];
}

/**
 * @param array<string, mixed> $entities
 * @return array<string, mixed>
 */
function strip_refinement_noise_from_entities(array $entities, string $rawMessage): array
{
    if (!is_cheaper_refinement_message($rawMessage)) {
        return $entities;
    }

    $noise = refinement_noise_keywords();
    if (!empty($entities['keywords']) && is_array($entities['keywords'])) {
        $entities['keywords'] = array_values(array_filter($entities['keywords'], static function ($kw) use ($noise): bool {
            $lower = to_lower(trim((string) $kw));
            return $lower !== '' && !in_array($lower, $noise, true);
        }));
    }

    return $entities;
}

/**
 * @param array<string, mixed> $memory
 * @return array<int, array<string, mixed>>
 */
function pick_cheaper_alternative_products(PDO $pdo, array $memory, array $entities, int $limit = 4): array
{
    $last = is_array($memory['last_suggested_products'] ?? null) ? $memory['last_suggested_products'] : [];
    $maxPrice = is_numeric($entities['max_price'] ?? null) ? (float) $entities['max_price'] : null;

    if ($maxPrice === null && is_numeric($memory['last_suggested_max_price'] ?? null)) {
        $maxPrice = round((float) $memory['last_suggested_max_price'] * 0.85, 2);
    }

    if ($last !== []) {
        $candidates = [];
        foreach ($last as $product) {
            if (!is_array($product)) {
                continue;
            }
            $fresh = refresh_product_for_followup($pdo, $product);
            $price = (float) ($fresh['price'] ?? 0);
            $stock = (int) ($fresh['stock_quantity'] ?? 0);
            if ($price <= 0 || $stock <= 0) {
                continue;
            }
            if ($maxPrice !== null && $price > $maxPrice + 0.01) {
                continue;
            }
            $candidates[] = $fresh;
        }

        usort($candidates, static fn (array $a, array $b): int => ((float) ($a['price'] ?? 0)) <=> ((float) ($b['price'] ?? 0)));

        if ($candidates !== []) {
            return array_slice($candidates, 0, $limit);
        }
    }

    $relaxed = $entities;
    $relaxed['keywords'] = [];
    $relaxed['category_like'] = null;
    $relaxed['product_type'] = null;
    $relaxed['brand'] = null;
    $relaxed['color'] = null;
    $relaxed['size'] = null;
    $relaxed['features'] = [];
    $relaxed['audience'] = null;
    $relaxed['sort_by'] = 'price_asc';

    return search_products_advanced($pdo, $relaxed, $limit);
}

/**
 * Yeni bir ürün konusu mu, yoksa önceki aramaya devam mı (daha ucuz, sadece siyah vb.)?
 */
function has_specific_product_query(array $entities): bool
{
    $generic = ['women', 'men', 'clothing', ''];
    $category = to_lower((string) ($entities['category_like'] ?? ''));
    $audience = to_lower((string) ($entities['audience'] ?? ''));
    return !empty($entities['product_type'])
        || in_array($audience, ['women', 'men'], true)
        || !empty($entities['_audience_correction'])
        || ($category !== '' && !in_array($category, $generic, true));
}

function is_strict_category_search(array $entities): bool
{
    $strictTypes = [
        'shirt', 'dress', 'skirts', 'blouse', 'pants', 'jacket', 'shoe', 'sneaker',
        'kitchen', 'headphone', 'phone', 'laptop', 'computer-tablet', 'tv', 'perfume',
        'watches', 'jewelry', 'men-shoes', 'women-shoes', 'bags', 'running', 'pet-food',
        'fiction', 'non-fiction', 'camera', 'printer', 'stationery', 'snacks', 'vitamins',
        'baby-care', 'kids-toys', 'kids-clothing', 'furniture', 'decor', 'bedding',
        'beauty', 'hair', 'makeup', 'skincare', 'speakers', 'smartwatch', 'rings',
        'cycling', 'fitness', 'outdoor', 'dog', 'cat', 'gadgets-accessories', 'smart-home',
        'outdoor-plants', 'gourmet', 'beverages', 'wellness', 'medical', 'cooking',
    ];
    $type = to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));
    return $type !== '' && in_array($type, $strictTypes, true);
}

/**
 * Oturum belleği + persona ile product_search entity'lerini günceller.
 *
 * @param array<string, mixed> $entities
 * @param array<string, mixed> $memory
 * @param array<string, mixed> $userProfile
 * @return array{entities: array, userProfile: array}
 */
function apply_product_search_session_context(
    array $entities,
    string $rawMessage,
    string $intent,
    array $memory,
    array $userProfile
): array {
    if ($intent !== 'product_search') {
        return ['entities' => $entities, 'userProfile' => $userProfile];
    }

    $inheritProductContext = should_inherit_product_search_context($entities, $rawMessage)
        || !empty($entities['_audience_correction']);
    $memoryEntities = is_array($memory['entities'] ?? null) ? $memory['entities'] : [];

    if ($memoryEntities !== []) {
        if ($inheritProductContext) {
            foreach (['max_price', 'min_price', 'category_like', 'product_type', 'color', 'size', 'brand', 'sort_by', 'audience'] as $k) {
                if (empty($entities[$k]) && !empty($memoryEntities[$k])) {
                    $entities[$k] = $memoryEntities[$k];
                }
            }
            if (empty($entities['features']) && !empty($memoryEntities['features']) && is_array($memoryEntities['features'])) {
                $entities['features'] = $memoryEntities['features'];
            }
            foreach (['category_like', 'color', 'size', 'brand'] as $k) {
                if (empty($entities[$k]) && !empty($memoryEntities[$k])) {
                    $entities[$k] = $memoryEntities[$k];
                }
            }
            if (empty($entities['keywords']) && !empty($memoryEntities['keywords']) && is_array($memoryEntities['keywords'])) {
                $entities['keywords'] = $memoryEntities['keywords'];
            }
        }

        if (!empty($entities['_audience_correction'])) {
            foreach (['product_type', 'category_like', 'color', 'size', 'brand', 'max_price', 'min_price', 'sort_by'] as $k) {
                if (!empty($memoryEntities[$k])) {
                    $entities[$k] = $memoryEntities[$k];
                }
            }
            if (!empty($memoryEntities['features']) && is_array($memoryEntities['features'])) {
                $entities['features'] = $memoryEntities['features'];
            }
            $genericCategories = ['women', 'men', 'clothing', ''];
            $inheritedCategory = to_lower((string) ($entities['category_like'] ?? ''));
            if (in_array($inheritedCategory, $genericCategories, true) && !empty($memoryEntities['product_type'])) {
                $entities['category_like'] = (string) $memoryEntities['product_type'];
            }
            $entities['_strict_audience'] = true;
        }

        if ((empty($entities['budget']['min']) && !empty($entities['min_price']))
            || (empty($entities['budget']['max']) && !empty($entities['max_price']))) {
            $entities['budget']['min'] = $entities['min_price'] ?? null;
            $entities['budget']['max'] = $entities['max_price'] ?? null;
        }
    }

    if (is_numeric($entities['max_price'] ?? null) || preg_match('/\b(under|below|budget|cheap|affordable|alt[ıi]|ucuz)\b/ui', $rawMessage)) {
        $userProfile['prefers_budget'] = true;
    }
    if (!empty($entities['category_like'])) {
        $userProfile['category_interest'] = (string) $entities['category_like'];
    } elseif (!empty($entities['product_type'])) {
        $userProfile['category_interest'] = (string) $entities['product_type'];
    }

    if ($inheritProductContext && empty($entities['category_like']) && !empty($userProfile['category_interest'])) {
        $entities['category_like'] = (string) $userProfile['category_interest'];
    }
    if ($inheritProductContext
        && $userProfile['prefers_budget'] === true
        && empty($entities['max_price'])
        && is_numeric($memory['last_suggested_max_price'] ?? null)) {
        $entities['max_price'] = round((float) $memory['last_suggested_max_price'] * 0.9, 2);
        $entities['budget']['max'] = $entities['max_price'];
        if (empty($entities['sort_by'])) {
            $entities['sort_by'] = 'price_asc';
        }
    }

    if ($inheritProductContext && is_cheaper_refinement_message($rawMessage)) {
        $prevMax = is_numeric($entities['max_price'] ?? null) ? (float) $entities['max_price']
            : (is_numeric($memory['last_suggested_max_price'] ?? null) ? (float) $memory['last_suggested_max_price']
            : (is_numeric($memoryEntities['max_price'] ?? null) ? (float) $memoryEntities['max_price'] : null));
        if (is_numeric($prevMax) && $prevMax > 0) {
            $entities['max_price'] = round($prevMax * 0.85, 2);
            $entities['budget']['max'] = $entities['max_price'];
            $entities['budget']['max_usd'] = $entities['max_price'];
            $entities['budget']['currency'] = 'USD';
            $entities['sort_by'] = 'price_asc';
        }
        $entities = strip_refinement_noise_from_entities($entities, $rawMessage);
        $entities['_cheaper_refinement'] = true;
    }

    if ($inheritProductContext && is_wireless_refinement_message($rawMessage)) {
        $entities = strip_wireless_refinement_noise_from_entities($entities);
        $entities['features'] = ['wireless'];
        $entities['_wireless_refinement'] = true;
    }

    return ['entities' => $entities, 'userProfile' => $userProfile];
}

function should_inherit_product_search_context(array $entities, string $rawMessage): bool
{
    $genericCategories = ['women', 'men', 'clothing', ''];
    $hasSpecificTopic = !empty($entities['product_type'])
        || (!empty($entities['category_like']) && !in_array(to_lower((string) $entities['category_like']), $genericCategories, true));

    if ($hasSpecificTopic) {
        return false;
    }

    if (preg_match('/\b(those|these|olan|olanları|onlari|onları|daha ucuz|cheaper|biraz daha ucuz|ucuz)\b/ui', $rawMessage)) {
        return true;
    }

    if (is_wireless_refinement_message($rawMessage)) {
        return true;
    }

    if (preg_match('/\b(sadece|only)\b/ui', $rawMessage)) {
        return (bool) preg_match(
            '/\b(siyah|beyaz|mavi|kırmızı|kirmizi|yeşil|yesil|black|white|blue|red|green|ucuz|pahalı|pahali|cheap|wireless|kablosuz|bluetooth)\b/ui',
            $rawMessage
        );
    }

    if (!empty($entities['category_like']) || !empty($entities['product_type'])) {
        return false;
    }

    $keywords = filter_product_search_keywords(is_array($entities['keywords'] ?? null) ? $entities['keywords'] : []);
    return $keywords === [];
}

function filter_product_search_keywords(array $keywords): array
{
    $stop = turkish_search_stop_words();
    $stop = array_merge($stop, ["göster", "goster", "show", "the", "for", "and", "veya"]);
    $out = [];
    foreach ($keywords as $kw) {
        $kw = trim((string) $kw);
        if ($kw === "" || mb_strlen($kw) < 3) {
            continue;
        }
        $lower = function_exists("to_lower") ? to_lower($kw) : strtolower($kw);
        if (in_array($lower, $stop, true)) {
            continue;
        }
        $out[] = $kw;
    }
    return array_values(array_unique($out));
}

/**
 * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
 */
function search_products_with_audience_fallback(PDO $pdo, array $entities, int $limit = 4): array
{
    $products = search_products_advanced($pdo, $entities, $limit);
    if ($products !== []) {
        return [$products, $entities];
    }

    if (is_numeric($entities['max_price'] ?? null)
        || is_numeric($entities['min_price'] ?? null)
        || is_strict_category_search($entities)) {
        return [[], $entities];
    }

    if (!empty($entities['_strict_audience'])) {
        return [[], $entities];
    }

    $audience = to_lower((string) ($entities['audience'] ?? ''));
    $type = to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));
    if (!in_array($audience, ['women', 'men'], true) || $type === '') {
        return [[], $entities];
    }

    $relaxed = $entities;
    $relaxed['audience'] = null;
    $relaxed['_audience_fallback'] = $audience . '_' . $type;
    $products = search_products_advanced($pdo, $relaxed, $limit);
    if ($products !== []) {
        return [$products, $relaxed];
    }

    return [[], $entities];
}

function search_products_advanced(PDO $pdo, array $entities, int $limit = 4, int $relaxLevel = 0): array
{
    if ($relaxLevel === 0) {
        $entities = expand_entities_for_product_search($entities);
    }

    $sql = "
        SELECT 
            p.product_id,
            p.name,
            p.price,
            p.image_url,
            p.description,
            p.sub_category,
            p.stock_quantity,
            COALESCE(c.category_name, '') AS category
        FROM products p
        LEFT JOIN categories c ON c.category_id = p.category_id
        WHERE 1=1
          AND COALESCE(p.stock_quantity, 0) > 0
    ";

    $params = [];

    if (isset($entities["max_price"]) && is_numeric($entities["max_price"])) {
        $sql .= " AND p.price <= ?";
        $params[] = (float) $entities["max_price"];
    }

    if (isset($entities["min_price"]) && is_numeric($entities["min_price"])) {
        $sql .= " AND p.price >= ?";
        $params[] = (float) $entities["min_price"];
    }

    $categoryTerms = category_like_db_search_terms(
        !empty($entities["category_like"]) ? (string) $entities["category_like"] : null,
        !empty($entities["product_type"]) ? (string) $entities["product_type"] : null,
        !empty($entities["audience"]) ? (string) $entities["audience"] : null
    );
    if ($categoryTerms !== []) {
        $catParts = [];
        foreach (array_slice($categoryTerms, 0, 12) as $term) {
            $like = "%" . to_lower($term) . "%";
            $catParts[] = "LOWER(p.name) LIKE ?";
            $catParts[] = "LOWER(c.category_name) LIKE ?";
            $catParts[] = "LOWER(p.sub_category) LIKE ?";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " AND (" . implode(" OR ", $catParts) . ")";
    }

    if (is_footwear_product_search($entities)) {
        $rawMessage = (string) ($entities['_raw_message'] ?? '');
        append_footwear_name_guard(
            $sql,
            $params,
            should_use_athletic_footwear_guard($entities, $rawMessage)
        );
    }

    $clothingType = to_lower((string) ($entities['product_type'] ?? $entities['category_like'] ?? ''));
    if ($clothingType === 'shirt') {
        append_clothing_type_guard($sql, $params, $entities);
    } elseif (in_array($clothingType, ['skirts', 'dress'], true)) {
        append_skirts_dress_guard($sql, $params, $clothingType);
    }

    $searchKeywords = filter_product_search_keywords(is_array($entities["keywords"] ?? null) ? $entities["keywords"] : []);
    // Türkçe konu zaten category_like ile çözüldüyse, İngilizce DB'de olmayan ek anahtar kelime AND filtresi uygulama.
    if ($searchKeywords !== [] && $categoryTerms === []) {
        $orParts = [];

        foreach (array_slice($searchKeywords, 0, 6) as $kw) {
            $orParts[] = "LOWER(p.name) LIKE ?";
            $orParts[] = "LOWER(c.category_name) LIKE ?";
            $orParts[] = "LOWER(p.sub_category) LIKE ?";

            $like = "%" . to_lower((string) $kw) . "%";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($orParts)) {
            $sql .= " AND (" . implode(" OR ", $orParts) . ")";
        }
    }

    if (($entities["audience"] ?? null) === "men") {
        $sql .= " AND (
            LOWER(c.category_name) IN (?, ?)
            OR LOWER(p.sub_category) LIKE ?
            OR LOWER(p.name) LIKE ?
        )
        AND LOWER(c.category_name) NOT IN (?, ?)";

        $params[] = "men's clothing";
        $params[] = "mens clothing";
        $params[] = "%men-%";
        $params[] = "%men's%";
        $params[] = "women's clothing";
        $params[] = "womens clothing";
    } elseif (($entities["audience"] ?? null) === "women") {
        $sql .= " AND (
            LOWER(c.category_name) IN (?, ?)
            OR LOWER(p.sub_category) LIKE ?
            OR LOWER(p.name) LIKE ?
            OR LOWER(p.name) LIKE ?
            OR LOWER(p.name) LIKE ?
        )";

        $params[] = "women's clothing";
        $params[] = "womens clothing";
        $params[] = "%women-%";
        $params[] = "%women's%";
        $params[] = "%womens%";
        $params[] = "% women %";

        // Tişört vb. dar aramalarda tişört guard zaten kadın ürününü seçer;
        // men's clothing altında yanlış sınıflandırılmış kadın ürünlerini eleme.
        if ($clothingType !== 'shirt' && $clothingType !== 'blouse' && $clothingType !== 'dress') {
            $sql .= " AND LOWER(c.category_name) NOT IN (?, ?)";
            $params[] = "men's clothing";
            $params[] = "mens clothing";
        }
    }

    if (!empty($entities["features"]) && is_array($entities["features"])) {
        foreach (array_slice($entities["features"], 0, 4) as $feature) {
            if ($feature === "noise_cancelling") {
                $sql .= " AND (
                    LOWER(p.name) LIKE ?
                    OR LOWER(p.name) LIKE ?
                    OR LOWER(p.name) LIKE ?
                    OR LOWER(p.description) LIKE ?
                )";

                $params[] = "%noise%";
                $params[] = "%cancel%";
                $params[] = "%anc%";
                $params[] = "%noise%";
                continue;
            }

            if ($feature === 'wireless') {
                $sql .= " AND (
                    LOWER(p.name) LIKE ?
                    OR LOWER(p.name) LIKE ?
                    OR LOWER(p.description) LIKE ?
                    OR LOWER(p.description) LIKE ?
                    OR LOWER(p.sub_category) LIKE ?
                )";
                $params[] = '%wireless%';
                $params[] = '%bluetooth%';
                $params[] = '%wireless%';
                $params[] = '%bluetooth%';
                $params[] = '%wireless%';
                continue;
            }

            $like = "%" . to_lower((string) $feature) . "%";
            $sql .= " AND (
                LOWER(p.name) LIKE ?
                OR LOWER(p.description) LIKE ?
                OR LOWER(p.sub_category) LIKE ?
            )";

            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
    }

    if (!empty($entities["color"])) {
        $like = "%" . strtolower((string) $entities["color"]) . "%";
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($entities["size"])) {
        $like = "%" . strtolower((string) $entities["size"]) . "%";
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    if (!empty($entities["brand"])) {
        $like = "%" . strtolower((string) $entities["brand"]) . "%";
        $sql .= " AND (LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)";
        $params[] = $like;
        $params[] = $like;
    }

    $sortBy = $entities["sort_by"] ?? "featured_price";
    $budgetFloorSearch = is_numeric($entities['min_price'] ?? null)
        && !is_numeric($entities['max_price'] ?? null);
    $prefersTshirt = !$budgetFloorSearch
        && (to_lower((string) ($entities['product_type'] ?? '')) === 'shirt'
        || to_lower((string) ($entities['category_like'] ?? '')) === 'shirt');
    foreach ($searchKeywords as $kw) {
        $kwLower = function_exists("to_lower") ? to_lower((string) $kw) : strtolower((string) $kw);
        if (preg_match('/^(tshirt|tişört|tisort|t-shirt)/i', $kwLower)) {
            $prefersTshirt = true;
            break;
        }
    }
    if ($budgetFloorSearch) {
        $prefersTshirt = false;
    }

    if ($prefersTshirt) {
        if (($entities['audience'] ?? null) === 'women') {
            $sql .= " ORDER BY CASE
                WHEN LOWER(p.sub_category) LIKE '%shirt%' AND (LOWER(p.name) LIKE '%women%' OR LOWER(p.name) LIKE '%womens%') THEN 0
                WHEN LOWER(p.name) LIKE '%tshirt%' THEN 1
                WHEN LOWER(p.name) LIKE '%shirt%' OR LOWER(p.name) LIKE '%t-shirt%' THEN 2
                ELSE 3
            END ASC";
        } else {
            $sql .= " ORDER BY CASE
                WHEN LOWER(p.name) LIKE '%tshirt%' THEN 0
                WHEN LOWER(p.name) LIKE '%shirt%' OR LOWER(p.name) LIKE '%t-shirt%' THEN 1
                ELSE 2
            END ASC";
        }
        if ($sortBy === "price_asc") {
            $sql .= ", p.price ASC";
        } elseif ($sortBy === "price_desc") {
            $sql .= ", p.price DESC";
        } elseif ($sortBy === "newest") {
            $sql .= ", p.product_id DESC";
        } else {
            $sql .= ", p.is_featured DESC, p.price ASC";
        }
    } elseif ($sortBy === "price_asc") {
        $sql .= " ORDER BY p.price ASC";
    } elseif ($sortBy === "price_desc") {
        $sql .= " ORDER BY p.price DESC";
    } elseif ($sortBy === "newest") {
        $sql .= " ORDER BY p.product_id DESC";
    } else {
        $sql .= " ORDER BY p.is_featured DESC, p.price ASC";
    }

    $sql .= " LIMIT " . (int) $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!empty($rows)) {
            return $rows;
        }

        if (is_numeric($entities['max_price'] ?? null) && (float) $entities['max_price'] > 0) {
            return [];
        }

        if (is_strict_category_search($entities)) {
            return [];
        }

        if ($relaxLevel === 0) {
            $r = $entities;
            $r["color"] = null;
            $r["size"] = null;
            $r["brand"] = null;
            return search_products_advanced($pdo, $r, $limit, 1);
        }

        if ($relaxLevel === 1) {
            $r = $entities;
            $r["keywords"] = [];
            return search_products_advanced($pdo, $r, $limit, 2);
        }

        if ($relaxLevel === 2) {
            $r = $entities;
            $r["category_like"] = null;
            $r["product_type"] = null;
            return search_products_advanced($pdo, $r, $limit, 3);
        }

        if ($relaxLevel >= 3) {
            return [];
        }

        return [];
    } catch (Throwable $e) {
        return [];
    }
}

function infer_product_search_intent(PDO $pdo, string $rawMessage, array $entities): bool
{
    $text = trim($rawMessage);
    if ($text === "" || mb_strlen($text) < 2) return false;
    if (function_exists('is_best_sellers_request') && is_best_sellers_request($text)) {
        return true;
    }
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

function search_closest_above_budget(PDO $pdo, array $entities, int $limit = 1): array
{
    if (!is_numeric($entities['max_price'] ?? null)) {
        return [];
    }
    $probe = $entities;
    $floor = (float) $entities['max_price'];
    $probe['max_price'] = null;
    $probe['min_price'] = $floor;
    $probe['sort_by'] = 'price_asc';
    return search_products_advanced($pdo, $probe, $limit, 0);
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

/**
 * Mesajdan sipariş numarası çıkarır (#12, order 12, sipariş 12).
 */
function parse_order_id_from_message(string $rawMessage): ?int
{
    if (preg_match('/#\s*(\d{1,10})\b/u', $rawMessage, $m)) {
        return (int) $m[1];
    }
    if (preg_match('/(?:sipari[sş]|order)\s*(?:no|numara|number|id)?\s*[#:]?\s*(\d{1,10})\b/iu', $rawMessage, $m)) {
        return (int) $m[1];
    }
    return null;
}

function localize_order_status_for_chat(string $status, string $lang): string
{
    $slug = strtolower(trim($status));
    if ($slug === '') {
        return $status;
    }
    if (function_exists('t')) {
        $label = t('orders.status.' . $slug, $status);
        if ($label !== 'orders.status.' . $slug) {
            return $label;
        }
    }
    $fallback = [
        'pending' => ['tr' => 'Beklemede', 'en' => 'Pending'],
        'processing' => ['tr' => 'Hazırlanıyor', 'en' => 'Processing'],
        'awaiting_payment' => ['tr' => 'Ödeme bekleniyor', 'en' => 'Awaiting payment'],
        'failed' => ['tr' => 'Ödeme başarısız', 'en' => 'Payment failed'],
        'cancelled' => ['tr' => 'İptal', 'en' => 'Cancelled'],
        'shipped' => ['tr' => 'Kargoda', 'en' => 'Shipped'],
        'delivered' => ['tr' => 'Teslim edildi', 'en' => 'Delivered'],
    ];
    return $fallback[$slug][$lang] ?? $fallback[$slug]['en'] ?? ucfirst($slug);
}

/**
 * Chatbot için kullanıcı siparişlerini DB'den okur (orders + order_items).
 */
function fetch_order_items_by_order_ids(PDO $pdo, array $orderIds): array
{
    if ($orderIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $map = [];
    try {
        $stmt = $pdo->prepare("
            SELECT oi.order_id, oi.quantity, COALESCE(p.name, 'Product') AS name
            FROM order_items oi
            LEFT JOIN products p ON p.product_id = oi.product_id
            WHERE oi.order_id IN ($placeholders)
        ");
        $stmt->execute($orderIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $oid = (int) ($row['order_id'] ?? 0);
            if ($oid < 1) {
                continue;
            }
            $map[$oid][] = $row;
        }
    } catch (Throwable $e) {
        return [];
    }
    return $map;
}

function build_order_status_reply_db(PDO $pdo, string $rawMessage, string $lang, ?int $userId): string
{
    static $orderStatusHelpersLoaded = false;
    if (!$orderStatusHelpersLoaded) {
        require_once __DIR__ . '/../orders/OrderStatusService.php';
        $orderStatusHelpersLoaded = true;
    }
    if ($userId === null || $userId < 1) {
        return $lang === 'tr'
            ? 'Sipariş durumunu görmek için önce giriş yapmalısın. Girişten sonra sipariş numaranı yazabilirsin (örn. sipariş #12) veya Siparişler sayfasına gidebilirsin.'
            : 'Please sign in to view your order status. After logging in, send your order number (e.g. order #12) or open the Orders page.';
    }

    $requestedId = parse_order_id_from_message($rawMessage);

    try {
        if ($requestedId !== null) {
            $stmt = $pdo->prepare("
                SELECT order_id, total_amount, status, payment_status, tracking_number, carrier, shipped_at, delivered_at, created_at
                FROM orders
                WHERE order_id = ? AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$requestedId, $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $orders = $row ? [$row] : [];
        } else {
            $stmt = $pdo->prepare("
                SELECT order_id, total_amount, status, payment_status, tracking_number, carrier, shipped_at, delivered_at, created_at
                FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 3
            ");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $e) {
        return build_order_status_reply($rawMessage, $lang);
    }

    if ($orders === []) {
        if ($requestedId !== null) {
            return $lang === 'tr'
                ? "Sipariş #{$requestedId} hesabına bağlı bir kayıt olarak bulunamadı. Numarayı kontrol edip tekrar dene."
                : "I couldn't find order #{$requestedId} on your account. Please check the number and try again.";
        }
        return $lang === 'tr'
            ? 'Henüz siparişin yok. İlk siparişini verdiğinde durumunu buradan veya Siparişler sayfasından takip edebilirsin.'
            : "You don't have any orders yet. After your first purchase, you can track status here or on the Orders page.";
    }

    $orderIds = array_map(static fn(array $o): int => (int) ($o['order_id'] ?? 0), $orders);
    $itemsByOrder = fetch_order_items_by_order_ids($pdo, $orderIds);

    $lines = [];
    if ($requestedId === null) {
        $lines[] = $lang === 'tr' ? 'Son siparişlerin:' : 'Your recent orders:';
    } else {
        $lines[] = $lang === 'tr' ? "Sipariş #{$requestedId}:" : "Order #{$requestedId}:";
    }

    foreach ($orders as $order) {
        $oid = (int) ($order['order_id'] ?? 0);
        $statusKey = resolve_order_display_status_key(
            (string) ($order['status'] ?? 'pending'),
            (string) ($order['payment_status'] ?? 'paid')
        );
        $statusLabel = localize_order_status_for_chat($statusKey, $lang);
        $total = number_format((float) ($order['total_amount'] ?? 0), 2);
        $date = (string) ($order['created_at'] ?? '');
        if ($date !== '') {
            $ts = strtotime($date);
            if ($ts !== false) {
                $date = date('d.m.Y H:i', $ts);
            }
        }
        $lines[] = "#{$oid} — {$statusLabel} (pending) — \${$total}" . ($date !== '' ? " — {$date}" : '');

        $tracking = trim((string) ($order['tracking_number'] ?? ''));
        if ($tracking !== '') {
            $carrier = trim((string) ($order['carrier'] ?? ''));
            $trackLine = $lang === 'tr'
                ? "  Takip no: {$tracking}"
                : "  Tracking: {$tracking}";
            if ($carrier !== '') {
                $trackLine .= $lang === 'tr' ? " ({$carrier})" : " ({$carrier})";
            }
            $lines[] = $trackLine;
        }

        $items = $itemsByOrder[$oid] ?? [];
        if ($items !== []) {
            $parts = [];
            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? 'Product');
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $parts[] = $qty > 1 ? "{$name} x{$qty}" : $name;
            }
            $lines[] = '  • ' . implode(', ', $parts);
        }
    }

    $lower = function_exists('to_lower') ? to_lower($rawMessage) : strtolower($rawMessage);
    if (preg_match('/\b(when|eta|estimated|arrive|arrival|teslim|ne zaman|nerede|where)\b/ui', $lower)) {
        $lines[] = '';
        $lines[] = $lang === 'tr'
            ? 'Bekleyen siparişler genelde 2-4 iş günü içinde kargoya verilir. Ayrıntılar için Siparişler sayfasını kullanabilirsin.'
            : 'Pending orders are usually shipped within 2-4 business days. See the Orders page for full details.';
    }

    return implode("\n", $lines);
}

function is_stock_followup_question(string $msg): bool
{
    if (preg_match('/\b(sizes?|beden|numara)\b/ui', $msg)) {
        return false;
    }
    return (bool) preg_match('/\b(stock|stok|stokta|in\s*stock|out\s*of\s*stock)\b/ui', $msg)
        || (bool) preg_match('/\b(available|availability)\b/ui', $msg);
}

function is_size_followup_question(string $msg, array $entities): bool
{
    if (!empty($entities["size"])) {
        return true;
    }
    return (bool) preg_match('/\b(sizes?|beden|numara)\b/ui', $msg);
}

function resolve_chat_product_sizes(array $product): array
{
    if (!function_exists("get_product_sizes")) {
        require_once __DIR__ . "/../functions.php";
    }
    $sizes = get_product_sizes($product);
    if ($sizes !== []) {
        return $sizes;
    }

    $name = function_exists('to_lower') ? to_lower((string) ($product['name'] ?? '')) : strtolower((string) ($product['name'] ?? ''));
    $sub = function_exists('to_lower') ? to_lower((string) ($product['sub_category'] ?? '')) : strtolower((string) ($product['sub_category'] ?? ''));
    $haystack = $name . ' ' . $sub;
    if (preg_match('/\b(shirt|tshirt|t-shirt|dress|blouse|jacket|hoodie|top)\b/u', $haystack)) {
        return ['S', 'M', 'L', 'XL'];
    }

    return [];
}

function refresh_product_for_followup(PDO $pdo, array $product): array
{
    $productId = (int) ($product["product_id"] ?? 0);
    if ($productId <= 0) {
        return $product;
    }
    try {
        $stmt = $pdo->prepare("
            SELECT
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.description,
                p.sub_category,
                p.stock_quantity,
                p.sizes,
                COALESCE(c.category_name, '') AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE p.product_id = ?
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : $product;
    } catch (Throwable $e) {
        return $product;
    }
}

function build_product_followup_reply(PDO $pdo, string $rawMessage, string $lang, array $memory, array $entities): string
{
    $products = $memory["last_suggested_products"] ?? [];
    if (!is_array($products) || $products === []) {
        if (!empty($memory["entities"]) && is_array($memory["entities"])) {
            $products = search_products_advanced($pdo, $memory["entities"], 1, 0);
        }
    }
    if ($products === []) {
        return $lang === "tr"
            ? "Hangi ürün hakkında konuştuğumuzu netleştirebilir misin? Önce bir ürün arayabilirsin."
            : "Which product do you mean? Try searching for a product first.";
    }

    $primary = refresh_product_for_followup($pdo, $products[0]);
    $name = (string) ($primary["name"] ?? "Product");

    if (is_size_followup_question($rawMessage, $entities)) {
        $requested = strtoupper((string) ($entities["size"] ?? ""));
        if ($requested === "" && preg_match('/\b(?:size|beden)\s+(?!(?:are|is|available|var|mevcut)\b)(xxs|xs|s|m|l|xl|xxl|2xl|3xl)\b/ui', $rawMessage, $m)) {
            $requested = strtoupper($m[1]);
        }
        if (preg_match('/\b(?:what|which|hangi)\s+sizes?\s+(?:are|is|available|var|mevcut)\b/ui', $rawMessage)) {
            $requested = '';
        }
        $sizes = resolve_chat_product_sizes($primary);
        if ($sizes === []) {
            return $lang === "tr"
                ? "{$name} için kayıtlı beden seçeneği yok. Stok durumunu ürün detay sayfasından veya müşteri hizmetlerinden kontrol edebilirsin."
                : "No size options are listed for {$name}. Check the product page or contact support for availability.";
        }
        $sizeList = implode(", ", $sizes);
        if ($requested !== "") {
            $normalized = array_map(static fn($v) => strtoupper((string) $v), $sizes);
            if (in_array($requested, $normalized, true)) {
                return $lang === "tr"
                    ? "Evet, {$name} için {$requested} beden mevcut. Mevcut bedenler: {$sizeList}."
                    : "Yes, size {$requested} is available for {$name}. Sizes: {$sizeList}.";
            }
            return $lang === "tr"
                ? "{$name} için {$requested} beden listede yok. Mevcut bedenler: {$sizeList}."
                : "Size {$requested} is not listed for {$name}. Available sizes: {$sizeList}.";
        }
        return $lang === "tr"
            ? "{$name} bedenleri: {$sizeList}."
            : "Sizes: {$sizeList}.";
    }

    if (is_stock_followup_question($rawMessage)) {
        $qty = (int) ($primary["stock_quantity"] ?? 0);
        if ($qty > 0) {
            return $lang === "tr"
                ? "{$name} şu anda stokta ({$qty} adet)."
                : "{$name} is in stock ({$qty} units available).";
        }
        return $lang === "tr"
            ? "{$name} şu anda stokta yok."
            : "{$name} is currently out of stock.";
    }

    return $lang === "tr"
        ? "Ürün hakkında beden veya stok sorabilirsin."
        : "Ask about size or stock for the product.";
}

function fetch_top_products(PDO $pdo, int $limit = 6): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.description,
                p.sub_category,
                p.stock_quantity,
                COALESCE(c.category_name, '') AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE COALESCE(p.stock_quantity, 0) > 0
            ORDER BY p.is_featured DESC, p.product_id DESC
            LIMIT ?
        ");

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function fetch_best_seller_products(PDO $pdo, int $limit = 4): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.description,
                p.sub_category,
                p.stock_quantity,
                COALESCE(c.category_name, '') AS category,
                COALESCE(SUM(oi.quantity), 0) AS units_sold
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN order_items oi ON oi.product_id = p.product_id
            LEFT JOIN orders o ON o.order_id = oi.order_id
                AND o.payment_status = 'paid'
                AND o.status <> 'cancelled'
            WHERE COALESCE(p.stock_quantity, 0) > 0
            GROUP BY
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.description,
                p.sub_category,
                p.stock_quantity,
                c.category_name
            ORDER BY units_sold DESC, p.is_featured DESC, p.product_id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            return $rows;
        }
    } catch (Throwable $e) {
        // Fall through to featured products when sales tables are unavailable.
    }

    return fetch_top_products($pdo, $limit);
}

function handle_intent_action(PDO $pdo, string $intent, string $rawMessage, string $lang, array $policyKnowledge, array $entities, ?int $userId = null, array $memory = []): array
{
    $reply = "";
    $suggestedProducts = [];
    $redirectUrl = null;
    $source = "rule_based";
    if ($intent === "shipping") $reply = build_shipping_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "returns") $reply = build_returns_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "payment") $reply = build_payment_reply($rawMessage, $policyKnowledge, $lang);
    elseif ($intent === "order_status") {
        $reply = build_order_status_reply_db($pdo, $rawMessage, $lang, $userId);
        $source = "order_lookup";
        $redirectUrl = function_exists('localized_path') ? localized_path('orders.php') : 'orders.php';
    }
    elseif ($intent === "cart_question") {
        $cartItems = is_array($memory["last_cart"] ?? null) ? $memory["last_cart"] : [];
        $reply = build_cart_reply($cartItems, $lang);
        $source = "cart_lookup";
    }
    elseif ($intent === "product_followup") {
        $reply = build_product_followup_reply($pdo, $rawMessage, $lang, $memory, $entities);
        $stored = $memory["last_suggested_products"] ?? [];
        $suggestedProducts = is_array($stored) ? array_slice($stored, 0, 6) : [];
        $source = "product_followup";
    }
    elseif ($intent === "product_search") {

    if ((function_exists('is_best_sellers_request') && is_best_sellers_request($rawMessage))
        || !empty($entities['_best_sellers_request'])) {
        $entities['_best_sellers_request'] = true;
        $suggestedProducts = fetch_best_seller_products($pdo, 4);
        $source = "best_sellers";
    } elseif (is_cheaper_refinement_message($rawMessage) && !empty($memory['last_suggested_products'])) {
        $suggestedProducts = pick_cheaper_alternative_products($pdo, $memory, $entities, 4);
    } else {
        [$suggestedProducts, $entities] = search_products_with_audience_fallback($pdo, $entities, 4);
    }
    $suggestedProducts = filter_products_for_entities($suggestedProducts, $entities);

    if (empty($suggestedProducts)) {
        if (is_numeric($entities['max_price'] ?? null)) {
            $reply = build_product_reply([], $entities, $lang);
            $closest = filter_products_for_entities(search_closest_above_budget($pdo, $entities, 1), $entities);
            if ($closest !== []) {
                $near = $closest[0];
                $nearPrice = number_format((float) ($near['price'] ?? 0), 2);
                $nearName = (string) ($near['name'] ?? 'Product');
                $reply .= $lang === 'tr'
                    ? "\n\nEn yakın seçenek: {$nearName} (\${$nearPrice})"
                    : "\n\nClosest option: {$nearName} (\${$nearPrice})";
            }
        } elseif (has_specific_product_query($entities) || is_strict_category_search($entities)) {
            $reply = build_product_reply([], $entities, $lang);
        } else {
            $suggestedProducts = fetch_top_products($pdo, 4);
            $reply = $lang === "tr"
                ? "Şunlara göz atabilirsin:"
                : "You may like these products:";
        }
    } else {
        $reply = build_product_reply($suggestedProducts, $entities, $lang);
    }

        $redirectUrl = build_products_redirect_url($entities);
        if ($source !== "best_sellers") {
            $source = "product_search";
        }
        } else {
        $budgetReply = try_budget_recommendation($pdo, $rawMessage);
        if (is_string($budgetReply) && $budgetReply !== "") { $reply = $budgetReply; $source = "budget_logic"; }
        else $reply = $lang === "tr" ? "Memnuniyetle yardımcı olurum. Ürün önerisi, kargo, iade veya sipariş takibi sorabilirsin." : "Happy to help. You can ask about product recommendations, shipping, returns, or order tracking.";
        }
        return [$reply, $suggestedProducts, $redirectUrl, $source, $entities];
    }


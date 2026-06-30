<?php

/**
 * @param array<int, int|string> $favoriteProductIds Browser wishlist product IDs (from cookie/API).
 */
function get_ai_recommendations(PDO $pdo, int $userId = 0, int $limit = 4, array $favoriteProductIds = []): array
{
    $categoryScores = [];
    $subCategoryScores = [];
    $excludeProductIds = parse_favorite_product_ids($favoriteProductIds);

    $applyCategoryScore = static function (int $categoryId, int $points) use (&$categoryScores): void {
        if ($categoryId > 0) {
            $categoryScores[$categoryId] = ($categoryScores[$categoryId] ?? 0) + $points;
        }
    };

    $applySubScore = static function (?string $subCategory, int $points) use (&$subCategoryScores): void {
        $sub = strtolower(trim((string) $subCategory));
        if ($sub !== '') {
            $subCategoryScores[$sub] = ($subCategoryScores[$sub] ?? 0) + $points;
        }
    };

    if ($excludeProductIds !== []) {
        $placeholders = implode(',', array_fill(0, count($excludeProductIds), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT product_id, category_id, sub_category
                FROM products
                WHERE product_id IN ($placeholders)
                  AND category_id IS NOT NULL
            ");
            $stmt->execute($excludeProductIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $applyCategoryScore((int) ($row['category_id'] ?? 0), 5);
                $applySubScore($row['sub_category'] ?? null, 4);
            }
        } catch (Throwable $e) {
            // Favorites are optional; continue with orders/random fallback.
        }
    }

    if ($userId > 0) {
        try {
            $orderStmt = $pdo->prepare("
                SELECT
                    p.category_id,
                    p.sub_category,
                    o.created_at
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.order_id
                INNER JOIN products p ON p.product_id = oi.product_id
                WHERE o.user_id = ?
                  AND o.status <> 'cancelled'
                  AND LOWER(COALESCE(o.payment_status, '')) IN ('paid', 'completed', 'success')
                  AND p.category_id IS NOT NULL
                ORDER BY o.created_at DESC
                LIMIT 40
            ");
            $orderStmt->execute([$userId]);

            foreach ($orderStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $createdAt = strtotime((string) ($row['created_at'] ?? ''));
                $isRecent = $createdAt !== false && $createdAt >= strtotime('-90 days');
                $categoryPoints = $isRecent ? 12 : 4;
                $subPoints = $isRecent ? 10 : 3;

                $applyCategoryScore((int) ($row['category_id'] ?? 0), $categoryPoints);
                $applySubScore($row['sub_category'] ?? null, $subPoints);
            }
        } catch (Throwable $e) {
            // Keep favorite scores if order lookup fails.
        }
    }

    if (!empty($categoryScores)) {
        arsort($categoryScores);
        arsort($subCategoryScores);

        $categoryIds = array_keys($categoryScores);
        $subCategories = array_keys($subCategoryScores);

        foreach ($subCategories as $subCategory) {
            $rows = recommendation_fetch_products(
                $pdo,
                $limit,
                $excludeProductIds,
                null,
                $subCategory
            );
            if ($rows !== []) {
                return $rows;
            }
        }

        foreach ($categoryIds as $categoryId) {
            $rows = recommendation_fetch_products(
                $pdo,
                $limit,
                $excludeProductIds,
                (int) $categoryId,
                null
            );
            if ($rows !== []) {
                return $rows;
            }
        }
    }

    return recommendation_fetch_products($pdo, $limit, $excludeProductIds, null, null);
}

/**
 * @param array<int, int> $excludeProductIds
 * @return array<int, array<string, mixed>>
 */
function recommendation_fetch_products(
    PDO $pdo,
    int $limit,
    array $excludeProductIds,
    ?int $categoryId = null,
    ?string $subCategory = null
): array {
    if ($limit < 1) {
        return [];
    }

    try {
        $where = ['COALESCE(p.stock_quantity, 0) > 0'];
        $params = [];

        if ($categoryId !== null && $categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }

        $sub = strtolower(trim((string) $subCategory));
        if ($sub !== '') {
            $where[] = 'LOWER(p.sub_category) = ?';
            $params[] = $sub;
        }

        if ($excludeProductIds !== []) {
            $where[] = 'p.product_id NOT IN (' . implode(',', array_fill(0, count($excludeProductIds), '?')) . ')';
            foreach ($excludeProductIds as $id) {
                $params[] = $id;
            }
        }

        $params[] = $limit;

        $stmt = $pdo->prepare("
            SELECT
                p.product_id,
                p.name,
                p.price,
                p.image_url,
                p.badges,
                p.stock_quantity,
                p.sub_category,
                p.description,
                c.category_name AS category
            FROM products p
            LEFT JOIN categories c ON c.category_id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.is_featured DESC, RAND()
            LIMIT ?
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

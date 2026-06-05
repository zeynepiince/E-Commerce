<?php

/**
 * @param array<int, int|string> $favoriteProductIds Browser wishlist product IDs (from cookie/API).
 */
function get_ai_recommendations(PDO $pdo, int $userId = 0, int $limit = 4, array $favoriteProductIds = []): array
{
    $categoryScores = [];
    $excludeProductIds = parse_favorite_product_ids($favoriteProductIds);

    if ($excludeProductIds !== []) {
        $placeholders = implode(',', array_fill(0, count($excludeProductIds), '?'));
        try {
            $stmt = $pdo->prepare("
                SELECT product_id, category_id
                FROM products
                WHERE product_id IN ($placeholders)
                  AND category_id IS NOT NULL
            ");
            $stmt->execute($excludeProductIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cat = (int) ($row['category_id'] ?? 0);
                if ($cat > 0) {
                    $categoryScores[$cat] = ($categoryScores[$cat] ?? 0) + 5;
                }
            }
        } catch (Throwable $e) {
            // Favorites are optional; continue with orders/random fallback.
        }
    }

    if ($userId > 0) {
        try {
            $orderStmt = $pdo->prepare("
                SELECT p.category_id
                FROM orders o
                INNER JOIN order_items oi ON oi.order_id = o.order_id
                INNER JOIN products p ON p.product_id = oi.product_id
                WHERE o.user_id = ?
                  AND p.category_id IS NOT NULL
            ");
            $orderStmt->execute([$userId]);

            foreach ($orderStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cat = (int) ($row['category_id'] ?? 0);
                if ($cat > 0) {
                    $categoryScores[$cat] = ($categoryScores[$cat] ?? 0) + 8;
                }
            }
        } catch (Throwable $e) {
            // Keep favorite scores if order lookup fails.
        }
    }

    if (!empty($categoryScores)) {
        arsort($categoryScores);
        $topCategory = (int) array_key_first($categoryScores);

        try {
            $excludeSql = '';
            $params = [$topCategory];
            if ($excludeProductIds !== []) {
                $excludeSql = ' AND p.product_id NOT IN (' . implode(',', array_fill(0, count($excludeProductIds), '?')) . ')';
                $params = array_merge($params, $excludeProductIds);
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
                WHERE p.category_id = ?
                  AND COALESCE(p.stock_quantity, 0) > 0
                  $excludeSql
                ORDER BY RAND()
                LIMIT ?
            ");

            $stmt->execute($params);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($rows !== []) {
                return $rows;
            }
        } catch (Throwable $e) {
            // fallback below
        }
    }

    try {
        $excludeSql = '';
        $params = [];
        if ($excludeProductIds !== []) {
            $excludeSql = ' AND p.product_id NOT IN (' . implode(',', array_fill(0, count($excludeProductIds), '?')) . ')';
            $params = $excludeProductIds;
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
            WHERE COALESCE(p.stock_quantity, 0) > 0
              $excludeSql
            ORDER BY p.is_featured DESC, RAND()
            LIMIT ?
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

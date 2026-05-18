<?php

function get_ai_recommendations(PDO $pdo, int $userId = 0, int $limit = 4): array
{
    $categoryScores = [];

    if ($userId > 0) {
        try {
            // Favorites = +5
            $favStmt = $pdo->prepare("
                SELECT p.category_id
                FROM user_favorites uf
                INNER JOIN products p ON p.product_id = uf.product_id
                WHERE uf.user_id = ?
                  AND p.category_id IS NOT NULL
            ");
            $favStmt->execute([$userId]);

            foreach ($favStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cat = (int) ($row['category_id'] ?? 0);
                if ($cat > 0) {
                    $categoryScores[$cat] = ($categoryScores[$cat] ?? 0) + 5;
                }
            }

            // Orders = +8
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
            $categoryScores = [];
        }
    }

    if (!empty($categoryScores)) {
        arsort($categoryScores);
        $topCategory = (int) array_key_first($categoryScores);

        try {
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
                ORDER BY RAND()
                LIMIT ?
            ");

            $stmt->bindValue(1, $topCategory, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!empty($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            // fallback aşağıda çalışacak
        }
    }

    try {
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
            ORDER BY p.is_featured DESC, RAND()
            LIMIT ?
        ");

        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
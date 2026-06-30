-- Bulk reclassification is applied via import_products.php?backfill_subcat=1&force=1
-- after infer_subcategory() / infer_category_id() improvements.
-- This migration only fixes known bad rows that slipped through substring matching.

UPDATE products SET sub_category = 'kitchen', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'home' LIMIT 1)
WHERE LOWER(name) LIKE '%wok%' AND sub_category NOT IN ('kitchen');

UPDATE products SET sub_category = 'kitchen', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'home' LIMIT 1)
WHERE LOWER(name) LIKE '%strainer%' AND sub_category NOT IN ('kitchen');

UPDATE products SET sub_category = 'watches', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) IN ('jewelery', 'jewelry') LIMIT 1)
WHERE LOWER(name) LIKE '%watch%' AND sub_category NOT IN ('watches', 'smartwatch');

UPDATE products SET sub_category = 'men-shoes', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'men''s clothing' LIMIT 1)
WHERE LOWER(name) REGEXP '(sneaker|cleat|trainer|loafer|oxford)' AND sub_category NOT IN ('men-shoes', 'women-shoes');

UPDATE products SET sub_category = 'pet-food', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'home' LIMIT 1)
WHERE LOWER(name) LIKE '%dog food%' AND sub_category <> 'pet-food';

-- Dress/frock products misclassified under Men's (e.g. "Short Frock" matched men/pants keyword "short").
UPDATE products SET sub_category = 'dress', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'women''s clothing' LIMIT 1)
WHERE (
    LOWER(name) LIKE '%frock%'
    OR (LOWER(name) LIKE '%dress%' AND LOWER(name) NOT LIKE '%dress shirt%')
    OR LOWER(description) LIKE '%trendy dress%'
    OR LOWER(description) LIKE '%playful%dress%'
)
AND category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'men''s clothing' LIMIT 1);

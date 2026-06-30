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

-- Food category + move groceries out of Home.
INSERT INTO categories (category_name)
SELECT 'Food' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1);

UPDATE products SET category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1)
WHERE LOWER(COALESCE(sub_category, '')) IN ('snacks', 'beverages', 'gourmet');

-- Groceries misclassified under Home (no/wrong sub_category).
UPDATE products SET sub_category = 'snacks', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1)
WHERE LOWER(name) REGEXP '(potato|onion|cucumber|tomato|carrot|cabbage|lettuce|broccoli|garlic|ginger|avocado|banana|strawberry|kiwi|mulberry|rice|pasta|flour|bread|cheese|popcorn|cereal)'
AND LOWER(COALESCE(sub_category, '')) NOT IN ('snacks', 'beverages', 'gourmet');

UPDATE products SET sub_category = 'beverages', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1)
WHERE LOWER(name) REGEXP '(cola|coke|pepsi|sprite|fanta|soda|juice|lemonade|mineral water|sparkling water|beer|wine|milk|smoothie|energy drink|soft drink)'
AND LOWER(COALESCE(sub_category, '')) NOT IN ('snacks', 'beverages', 'gourmet');

-- Beauty / sports / pet etc. → correct category_id from sub_category (site nav uses sub_category parent).
UPDATE products SET category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'home' LIMIT 1)
WHERE LOWER(sub_category) IN ('perfume', 'makeup', 'hair', 'skincare', 'kitchen', 'bedding', 'decor', 'furniture', 'books', 'fiction', 'non-fiction', 'kids-books', 'education', 'pet-food', 'pet-toys', 'dog', 'cat', 'stationery', 'desk', 'office-supplies', 'outdoor-plants', 'garden-tools', 'outdoor-furniture', 'vitamins', 'medical', 'wellness', 'baby-toys', 'baby-care', 'baby-clothing', 'art-materials', 'craft-supplies', 'sewing');

UPDATE products SET category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'fashion' LIMIT 1)
WHERE LOWER(sub_category) IN ('running', 'cycling', 'fitness', 'outdoor', 'kids-toys', 'kids-clothing', 'games', 'school', 'puzzles', 'board-games', 'educational-toys', 'action-figures', 'car-electronics', 'car-care', 'car-accessories');

UPDATE products SET category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'electronics' LIMIT 1)
WHERE LOWER(sub_category) IN ('smartwatch', 'headphones', 'gadgets-accessories');

-- Women's tops wrongly stored as men's sub_category "shirt".
UPDATE products SET sub_category = 'women-tops', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'women''s clothing' LIMIT 1)
WHERE sub_category = 'shirt'
AND (
    LOWER(name) LIKE '%women%' OR LOWER(name) LIKE '%womens%' OR LOWER(name) LIKE '%ladies%'
    OR LOWER(name) LIKE '%dress%' OR LOWER(name) LIKE '%blouse%' OR LOWER(name) LIKE '%skirt%'
    OR category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'women''s clothing' LIMIT 1)
);

-- Non-apparel products wrongly under Men's clothing.
UPDATE products SET sub_category = 'snacks', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1)
WHERE category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'men''s clothing' LIMIT 1)
AND LOWER(name) REGEXP '(chicken|turkey|beef|pork|meat|fish|salmon|shrimp|apple|potato|onion|cucumber|tomato|carrot|rice|pasta|bread|cheese|egg|milk|cola|coke|pepsi|juice|soda|snack|cereal|banana|strawberry|kiwi|lemon|mulberry|flour|sugar|nuts|popcorn)';

UPDATE products SET sub_category = 'gourmet', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'food' LIMIT 1)
WHERE category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'men''s clothing' LIMIT 1)
AND LOWER(name) REGEXP '(cooking oil|olive oil|vegetable oil|sunflower oil|vinegar|honey|jam|chocolate|spice|seasoning)';

UPDATE products SET sub_category = 'dress', category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'women''s clothing' LIMIT 1)
WHERE category_id = (SELECT category_id FROM categories WHERE LOWER(category_name) = 'men''s clothing' LIMIT 1)
AND (LOWER(name) LIKE '%dress%' OR LOWER(name) LIKE '%frock%' OR LOWER(name) LIKE '%gown%' OR LOWER(name) LIKE '%skirt%')
AND LOWER(name) NOT LIKE '%dress shirt%';

-- Full reclassification: import_products.php?backfill_subcat=1&force=1

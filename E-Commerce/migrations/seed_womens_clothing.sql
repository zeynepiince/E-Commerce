-- Kadın giyim kategorisini doldurur (DummyJSON'da kadın tişört yok; seed + import ile tamamlanır).
--
-- 1) DummyJSON kadın kategorilerini içe aktar:
--    import_products.php?women=1
--    veya CLI: RUN_PRODUCT_IMPORT=1 php import_products.php women=1  (GET via browser URL)
--
-- 2) Bu dosyayı çalıştır (kadın tişört seed):
--    mysql -u root -p chatbotv2_db < migrations/seed_womens_clothing.sql

INSERT INTO products (external_id, name, description, price, category_id, sub_category, image_url, stock_quantity, is_featured)
VALUES
(
    9101,
    'Women Cotton V-Neck T-Shirt',
    'Soft cotton v-neck tee for everyday wear. Lightweight, breathable fabric with a relaxed fit.',
    22.99,
    (SELECT category_id FROM categories WHERE LOWER(category_name) IN ('women''s clothing', 'womens clothing') LIMIT 1),
    'shirt',
    'https://cdn.dummyjson.com/product-images/tops/blue-frock/thumbnail.webp',
    100,
    1
),
(
    9102,
    'Women Striped T-Shirt',
    'Classic striped women''s t-shirt with a comfortable crew neck. Easy to pair with jeans or skirts.',
    19.99,
    (SELECT category_id FROM categories WHERE LOWER(category_name) IN ('women''s clothing', 'womens clothing') LIMIT 1),
    'shirt',
    'https://cdn.dummyjson.com/product-images/tops/gray-dress/thumbnail.webp',
    100,
    0
),
(
    9103,
    'Women Oversized Tee',
    'Trendy oversized women''s t-shirt in premium cotton. Dropped shoulders and loose silhouette.',
    24.99,
    (SELECT category_id FROM categories WHERE LOWER(category_name) IN ('women''s clothing', 'womens clothing') LIMIT 1),
    'shirt',
    'https://cdn.dummyjson.com/product-images/tops/short-frock/thumbnail.webp',
    100,
    0
),
(
    9104,
    'Women Basic White T-Shirt',
    'Essential white women''s t-shirt with a clean fit. Wardrobe staple for layering or solo wear.',
    17.99,
    (SELECT category_id FROM categories WHERE LOWER(category_name) IN ('women''s clothing', 'womens clothing') LIMIT 1),
    'shirt',
    'https://cdn.dummyjson.com/product-images/tops/tartan-dress/thumbnail.webp',
    100,
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    price = VALUES(price),
    category_id = VALUES(category_id),
    sub_category = VALUES(sub_category),
    image_url = VALUES(image_url),
    stock_quantity = COALESCE(products.stock_quantity, VALUES(stock_quantity));

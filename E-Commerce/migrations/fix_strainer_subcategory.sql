-- Fine Mesh Strainer was incorrectly tagged men-shoes in import data.
-- Run: mysql -u root -proot -P 8888 chatbotv2_db < migrations/fix_strainer_subcategory.sql

UPDATE products
SET sub_category = 'cookware'
WHERE name = 'Fine Mesh Strainer'
  AND sub_category = 'men-shoes';

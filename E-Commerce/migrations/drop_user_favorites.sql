-- Favoriler yalnızca tarayıcı localStorage (zera_favorites) ile tutulur.
-- Run: mysql -u root -proot -P 8889 chatbotv2_db < migrations/drop_user_favorites.sql

DROP TABLE IF EXISTS user_favorites;

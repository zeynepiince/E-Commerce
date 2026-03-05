<?php
$host = "localhost";
$db   = "chatbotv2_db";
$user = "root";
$pass = "root";   // MAMP / 8888 için
$port = 8888;

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

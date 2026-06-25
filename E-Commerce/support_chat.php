<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chatbot/helpers.php';
require_once __DIR__ . '/chatbot/responses.php';
require_once __DIR__ . '/chatbot/actions.php';
require_once __DIR__ . '/chatbot/ai.php';
require_once __DIR__ . '/chatbot/intent.php';
require_once __DIR__ . '/chatbot/consistency.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['reply' => 'Method not allowed.']);
    exit;
}

csrf_require(true);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

require __DIR__ . '/chatbot/serve_message.php';

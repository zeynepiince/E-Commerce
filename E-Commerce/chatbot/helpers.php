<?php

function to_lower(string $text): string
{
    return function_exists("mb_strtolower") ? mb_strtolower($text, "UTF-8") : strtolower($text);
}

function get_chat_user_name(): string
{
    $raw = trim((string) ($_SESSION["user_name"] ?? ""));
    if ($raw === "") return "";
    // keep it short/safe for chat responses
    $clean = preg_replace('/[^\p{L}\p{N}\s\-\._]/u', '', $raw);
    $clean = trim((string) $clean);
    return mb_substr($clean, 0, 32);
}


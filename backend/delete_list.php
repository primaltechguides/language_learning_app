<?php
// backend/delete_list.php

require_once 'utils.php'; // Include the utility file

header('Content-Type: application/json');

// Disable error reporting in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);
$topic = isset($input['topic']) ? trim($input['topic']) : '';
$language_code = isset($input['language']) ? trim($input['language']) : 'ru'; // Default to 'ru'

if (empty($topic)) {
    http_response_code(400);
    echo json_encode(['error' => 'Topic is required.']);
    exit;
}

$config = include '../config.php';

// Validate the selected language
$is_valid_language = false;
foreach ($config['languages'] as $lang) {
    if ($lang['code'] === $language_code) {
        $is_valid_language = true;
        break;
    }
}

if (!$is_valid_language) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported language selected.']);
    exit;
}

// Load existing word lists
$word_lists = [];
if (file_exists($config['save_file'])) {
    $word_lists = json_decode(file_get_contents($config['save_file']), true);
    if (!is_array($word_lists)) {
        $word_lists = [];
    }
}

// Normalize topic for case-insensitive matching
$normalized_topic = strtolower(trim($topic));

$found = false;
foreach ($word_lists as $key => $phrases) {
    // Normalize the key for case-insensitive comparison
    if (strtolower($key) === $normalized_topic) {
        unset($word_lists[$key]);
        $found = true;
        break; // Stop once the topic is found and removed
    }
}

if ($found) {
    file_put_contents($config['save_file'], json_encode($word_lists, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Topic not found.']);
}
?>

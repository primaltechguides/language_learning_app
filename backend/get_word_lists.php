<?php
// backend/get_word_lists.php

header('Content-Type: application/json');

// Disable error reporting in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$config = include '../config.php';

// Determine the action based on the 'action' query parameter
$action = isset($_GET['action']) ? trim($_GET['action']) : 'get_word_lists';

if ($action === 'get_languages') {
    // Validate that 'languages' array exists
    if (!isset($config['languages']) || !is_array($config['languages'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Languages configuration is missing or invalid.']);
        exit;
    }

    // Return the list of supported languages
    $languages = array_map(function($lang) {
        return [
            'code' => $lang['code'],
            'name' => $lang['name']
        ];
    }, $config['languages']);
    
    echo json_encode($languages);
    exit;
} elseif ($action === 'get_word_lists') {
    // Retrieve word lists based on the selected language
    $language_code = isset($_GET['language']) ? trim($_GET['language']) : 'ru'; // Default to 'ru'

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
        $all_word_lists = json_decode(file_get_contents($config['save_file']), true);
        if (is_array($all_word_lists)) {
            // Filter word lists based on language code
            foreach ($all_word_lists as $key => $phrases) {
                if (strpos($key, "_{$language_code}_") !== false) {
                    $word_lists[$key] = $phrases;
                }
            }
        }
    }

    echo json_encode($word_lists);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action specified.']);
    exit;
}
?>

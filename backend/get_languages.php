<?php
// backend/get_languages.php

require_once 'utils.php'; // Include the utility file if utility functions are needed

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$config = include '../config.php';

$languages = array_map(function($lang) {
    return [
        'code' => $lang['code'],
        'name' => $lang['name']
    ];
}, $config['languages']);

echo json_encode($languages);
?>

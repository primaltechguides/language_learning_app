<?php
// backend/clear_cache.php

header('Content-Type: application/json');

require_once 'utils.php'; // Include the utility file

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$config = include '../config.php';
$cache_dir = $config['sound_cache'];

if (!is_dir($cache_dir)) {
    echo json_encode(['success' => true, 'message' => 'Cache directory does not exist.']);
    exit;
}

$files = glob($cache_dir . '/*.mp3');

foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

echo json_encode(['success' => true, 'message' => 'Audio cache cleared successfully.']);
?>

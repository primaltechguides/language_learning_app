<?php
// backend/utils.php

/**
 * Dynamically constructs the base URL based on the current request.
 *
 * @return string The base URL.
 */
function getBaseUrl() {
    // Determine the protocol (HTTP or HTTPS)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
                $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    
    // Get the host (e.g., localhost, example.com)
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the script's directory path (e.g., /language_learning_app/backend)
    $script = $_SERVER['SCRIPT_NAME'];
    $path = dirname($script);
    
    // Ensure the path ends with a slash
    $path = rtrim($path, '/') . '/';
    
    // Combine to form the base URL
    return $protocol . $host . $path;
}

/**
 * Sanitizes a filename by removing unwanted characters and formatting it consistently.
 *
 * @param string $filename The original filename.
 * @return string The sanitized filename.
 */
function sanitize_filename($filename) {
    // Convert to lowercase for consistency
    $filename = strtolower($filename);
    
    // Remove all characters that are not alphanumeric or spaces
    $filename = preg_replace('/[^a-z0-9 ]/', '', $filename);
    
    // Replace spaces with underscores
    $filename = preg_replace('/\s+/', '_', $filename);
    
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    
    // Trim leading and trailing underscores
    $filename = trim($filename, '_');
    
    // Limit filename length to 50 characters to prevent filesystem issues
    if (strlen($filename) > 50) {
        $filename = substr($filename, 0, 50);
    }
    
    // If filename is empty after sanitization, use a default name with a unique identifier
    if (empty($filename)) {
        $filename = 'audio_' . uniqid();
    }
    
    return $filename;
}
?>

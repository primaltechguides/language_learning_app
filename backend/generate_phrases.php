<?php
// backend/generate_phrases.php

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

// Find language configurations
$language_config = null;
foreach ($config['languages'] as $lang) {
    if ($lang['code'] === $language_code) {
        $language_config = $lang;
        break;
    }
}

if (!$language_config) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported language selected.']);
    exit;
}

// Define the OpenAI API endpoint
$api_url = 'https://api.openai.com/v1/chat/completions';

// Prepare the headers
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $config['openai_api_key']
];

// Prepare the data payload with JSON response format
$data = [
    'model' => 'gpt-4',
    'messages' => [
        ['role' => 'system', 'content' => 'Do not use markdown formatting, * or #'],
        ['role' => 'system', 'content' => 'When writing numbered lists, don\'t write the numbers e.g 1.'],
        ['role' => 'system', 'content' => 'Generate the output in valid JSON format. The JSON should be an array of two-element arrays, where the first element is the English phrase and the second is its translation.'],
        ['role' => 'user', 'content' => "Generate 15 random phrases in English and their {$language_config['name']} translations about {$topic}. The phrases need to be simple for a language learner."]
    ],
    'temperature' => 0.7,
    'max_tokens' => 500,
    'n' => 1,
    'stop' => null
];

// Initialize cURL session
$ch = curl_init($api_url);

// Set cURL options
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute the request
$response = curl_exec($ch);

// Check for cURL errors
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

// Get HTTP status code
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Handle non-200 responses
if ($http_status !== 200) {
    $error_response = json_decode($response, true);
    $error_message = isset($error_response['error']['message']) ? $error_response['error']['message'] : 'Unknown error';
    http_response_code($http_status);
    echo json_encode(['error' => 'OpenAI API Error: ' . $error_message]);
    exit;
}

// Decode the successful response
$decoded_response = json_decode($response, true);

// Extract the generated text
if (isset($decoded_response['choices'][0]['message']['content'])) {
    $generated_text = $decoded_response['choices'][0]['message']['content'];

    // Attempt to decode the response as JSON
    $generated_phrases = json_decode($generated_text, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to parse OpenAI response as JSON.']);
        exit;
    }

    // Validate the structure
    if (!is_array($generated_phrases)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid response format from OpenAI.']);
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

    // Determine new topic name
    $topic_prefix = "{$topic}_{$language_code}_";
    $existing_topics = array_filter(array_keys($word_lists), function($key) use ($topic_prefix) {
        return strpos($key, $topic_prefix) === 0;
    });

    $max_num = 0;
    foreach ($existing_topics as $existing_topic) {
        $parts = explode('_', $existing_topic);
        $last_part = end($parts);
        if (is_numeric($last_part)) {
            $num = (int)$last_part;
            if ($num > $max_num) {
                $max_num = $num;
            }
        }
    }

    $new_topic = "{$topic}_{$language_code}_" . ($max_num + 1);

    // Save the new phrases
    $word_lists[$new_topic] = $generated_phrases;
    file_put_contents($config['save_file'], json_encode($word_lists, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Return success response
    echo json_encode([
        'success' => true, 
        'topic' => $new_topic, 
        'language_code' => $language_code,
        'language_name' => $language_config['name'],
        'phrases' => $generated_phrases
    ]);

} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate phrases.']);
}
?>

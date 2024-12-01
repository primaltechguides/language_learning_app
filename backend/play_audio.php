<?php
// backend/play_audio.php

require_once __DIR__ . '/../vendor/autoload.php'; // Ensure Composer autoload is included
require_once 'utils.php'; // Include the utility file

use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;

header('Content-Type: application/json');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Get the input data
$input = json_decode(file_get_contents('php://input'), true);
$filename = isset($input['filename']) ? trim($input['filename']) : '';
$text = isset($input['text']) ? trim($input['text']) : '';
$tts_service = isset($input['tts_service']) ? trim($input['tts_service']) : 'google'; // Default to Google
$language_code = isset($input['language']) ? trim($input['language']) : 'ru'; // Default to 'ru'
$speed = isset($input['speed']) ? trim($input['speed']) : '1'; // Default to '1'

// Validate inputs
if (empty($filename) || empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename and text inputs are required.']);
    exit;
}

$config = include '../config.php';

// Validate speed
if (!in_array($speed, $config['available_speeds'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid speed selected.']);
    exit;
}

// Sanitize filename using utility function
$sanitized_filename = sanitize_filename($filename);

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

// Modify filename to include TTS service, language code, and speed to maintain separate files
$filename_mp3 = "{$sanitized_filename}_{$tts_service}_{$language_code}_{$speed}x.mp3";
$file_path = "{$config['sound_cache']}/{$filename_mp3}";

// Function to generate audio using Google TTS
function generate_google_tts($text, $config, $file_path, $language_config, $speed) {
    // Initialize Google TTS client
    $client = new TextToSpeechClient([
        'keyFilePath' => $config['google_service_account_json'] // Ensure this path is correct
    ]);

    // Adjust speed using speaking rate (Google TTS supports speaking_rate in AudioConfig)
    // The speaking_rate can be a value between 0.25 and 4.0
    $speaking_rate = floatval($speed);
    if ($speaking_rate < 0.25) {
        $speaking_rate = 0.25;
    } elseif ($speaking_rate > 4.0) {
        $speaking_rate = 4.0;
    }

    // Set the text input to be synthesized
    $synthesisInputText = (new SynthesisInput())
        ->setText($text);

    // Build the voice request
    $voice = (new VoiceSelectionParams())
        ->setLanguageCode(substr($language_config['tts_voice_google'], 0, 5)) // e.g., 'ru-RU'
        ->setName($language_config['tts_voice_google']); // Full voice name from config

    // Select the type of audio encoding
    $audioConfig = (new AudioConfig())
        ->setAudioEncoding(AudioEncoding::MP3)
        ->setSpeakingRate($speaking_rate); // Set the speaking rate

    // Perform the text-to-speech request
    try {
        $response = $client->synthesizeSpeech($synthesisInputText, $voice, $audioConfig);
    } catch (Exception $e) {
        $client->close();
        http_response_code(500);
        echo json_encode(['error' => 'Google TTS API Error: ' . $e->getMessage()]);
        exit;
    }

    // Get the audio content from the response
    $audioContent = $response->getAudioContent();

    // Ensure the sound_cache directory exists
    if (!is_dir(dirname($file_path))) {
        mkdir(dirname($file_path), 0755, true);
    }

    // Save the audio file
    file_put_contents($file_path, $audioContent);

    // Close the client
    $client->close();

    return true;
}

// Function to generate audio using OpenAI TTS
function generate_openai_tts($text, $config, $file_path, $language_config, $speed) {
    // Define the OpenAI TTS API endpoint
    $api_url = $config['openai_tts_endpoint']; // Ensure this matches the actual endpoint

    // Prepare the headers
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['openai_api_key']
    ];

    // Prepare the data payload
    $data = [
        'model' => $config['tts_model'],
        'input' => $text,
        'voice' => $language_config['tts_voice_openai'],
        'speed' => floatval($speed) // Include speed parameter if supported
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
        $error_msg = curl_error($ch);
        curl_close($ch);
        http_response_code(500);
        echo json_encode(['error' => 'cURL Error: ' . $error_msg]);
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
        echo json_encode(['error' => 'OpenAI TTS API Error: ' . $error_message]);
        exit;
    }

    // Save the audio content
    $audio_content = $response; // Assuming the API returns raw MP3 binary data

    // Ensure the sound_cache directory exists
    if (!is_dir(dirname($file_path))) {
        mkdir(dirname($file_path), 0755, true);
    }

    // Save the audio file
    file_put_contents($file_path, $audio_content);

    return true;
}

// Check if audio file already exists
if (!file_exists($file_path)) {
    if ($tts_service === 'google') {
        $success = generate_google_tts($text, $config, $file_path, $language_config, $speed);
    } elseif ($tts_service === 'openai') {
        $success = generate_openai_tts($text, $config, $file_path, $language_config, $speed);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid TTS service selected.']);
        exit;
    }

    if (!$success) {
        // The respective TTS function handles error responses
        exit;
    }
}

// Generate the audio URL dynamically
$base_url = getBaseUrl();
$audio_url = "{$base_url}sound_cache/{$filename_mp3}";

echo json_encode(['audio_url' => $audio_url]);
?>

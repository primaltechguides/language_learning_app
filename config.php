
<?php
// config.php

return [
    'openai_api_key' => 'sk-proj-YOURKEY',
    'google_tts_api_key' => 'your_google_tts_api_key',
    'sound_cache' => __DIR__ . '/backend/sound_cache',
    'save_file' => __DIR__ . '/word_lists.json',
    'languages' => [
        [
            'code' => 'es',
            'name' => 'Spanish',
            'tts_voice_openai' => 'alloy', // Desired voice for OpenAI TTS
            'tts_voice_google' => 'ru-RU-Wavenet-D' // Desired voice for Google TTS
        ],
        [
            'code' => 'ru',
            'name' => 'Russian',
            'tts_voice_openai' => 'alloy', // Example OpenAI voice
            'tts_voice_google' => 'es-ES-Wavenet-A' // Example Google voice
        ],
        [
            'code' => 'fr',
            'name' => 'French',
            'tts_voice_openai' => 'alloy', // Example OpenAI voice
            'tts_voice_google' => 'fr-FR-Wavenet-B' // Example Google voice
        ],
        // Add more languages as needed
    ],
    'tts_model' => 'tts-1', // TTS model name as per OpenAI's documentation
    'openai_tts_endpoint' => 'https://api.openai.com/v1/audio/speech', // Replace with actual endpoint if different
    'google_service_account_json' => __DIR__ . '/path_to_your_google_service_account.json', // Path to your Google Service Account JSON
    'available_speeds' => ['0.5', '0.75', '1', '1.25', '1.5', '1.75', '2'] // Define supported speeds
];
?>

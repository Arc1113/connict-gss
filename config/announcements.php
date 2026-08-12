<?php

return [
    'provider_voices' => [
        'azure' => [
            'en-PH-JamesNeural' => 'en-PH',
            'en-PH-RosaNeural' => 'en-PH',
            'fil-PH-AngeloNeural' => 'fil-PH',
            'fil-PH-BlessicaNeural' => 'fil-PH',
        ],
        'kokoro' => [
            'am_michael' => 'en-US',
            'am_fenrir' => 'en-US',
            'af_heart' => 'en-US',
            'af_bella' => 'en-US',
        ],
    ],
    'provider_voice_catalog' => [
        'kokoro' => [
            'am_michael' => [
                'label' => 'Michael',
                'locale' => 'en-US',
                'gender' => 'male',
                'accent' => 'American English',
                'description' => 'Warm, clear male voice for professional ceremony delivery.',
            ],
            'am_fenrir' => [
                'label' => 'Fenrir',
                'locale' => 'en-US',
                'gender' => 'male',
                'accent' => 'American English',
                'description' => 'Confident male alternative for voice comparison.',
            ],
            'af_heart' => [
                'label' => 'Heart',
                'locale' => 'en-US',
                'gender' => 'female',
                'accent' => 'American English',
                'description' => 'Natural, balanced female voice.',
            ],
            'af_bella' => [
                'label' => 'Bella',
                'locale' => 'en-US',
                'gender' => 'female',
                'accent' => 'American English',
                'description' => 'Expressive female alternative for voice comparison.',
            ],
        ],
    ],
    'voices' => [
        'en-PH-JamesNeural' => 'en-PH',
        'en-PH-RosaNeural' => 'en-PH',
        'fil-PH-AngeloNeural' => 'fil-PH',
        'fil-PH-BlessicaNeural' => 'fil-PH',
    ],
    'speaking_rate_percent' => [
        'minimum' => -20,
        'maximum' => 10,
    ],
    'pause_ms' => [
        'minimum' => 0,
        'maximum' => 1500,
    ],
    'duration_words_per_minute' => [
        'minimum' => 145,
        'maximum' => 165,
    ],
    'default_output_format' => 'audio-24khz-48kbitrate-mono-mp3',
    'voice_comparison' => [
        'maximum_voices' => 4,
        'maximum_active_samples_per_user' => 12,
        'retention_minutes' => 60,
        'directory' => 'announcement-voice-samples',
    ],
    'batch_generation' => [
        'maximum_students' => 50,
        'queue' => 'tts-preparation',
    ],
    'azure' => [
        'enabled' => (bool) env('AZURE_SPEECH_ENABLED', false),
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION'),
        'endpoint' => env('AZURE_SPEECH_ENDPOINT'),
        'connect_timeout_seconds' => (int) env('AZURE_SPEECH_CONNECT_TIMEOUT', 5),
        'request_timeout_seconds' => (int) env('AZURE_SPEECH_REQUEST_TIMEOUT', 30),
        'maximum_retries' => (int) env('AZURE_SPEECH_MAX_RETRIES', 3),
        'retry_base_delay_ms' => 250,
        'retry_jitter_ms' => 100,
        'maximum_retry_delay_ms' => 2000,
        'maximum_retry_after_seconds' => 2,
        'maximum_response_bytes' => (int) env('AZURE_SPEECH_MAX_RESPONSE_BYTES', 15 * 1024 * 1024),
        'monthly_character_budget' => (int) env('AZURE_SPEECH_MONTHLY_CHARACTER_BUDGET', 500000),
        'budget_warning_percent' => (int) env('AZURE_SPEECH_BUDGET_WARNING_PERCENT', 80),
        'user_agent' => 'GraduationStageSystem/1.0',
        'candidate_disk' => 'local',
        'candidate_directory' => 'announcement-candidates',
    ],
    'kokoro' => [
        'enabled' => (bool) env('LOCAL_TTS_ENABLED', true),
        'base_url' => rtrim((string) env('LOCAL_TTS_URL', 'http://local-tts:8880'), '/'),
        'connect_timeout_seconds' => (int) env('LOCAL_TTS_CONNECT_TIMEOUT', 2),
        'request_timeout_seconds' => (int) env('LOCAL_TTS_REQUEST_TIMEOUT', 90),
        'maximum_response_bytes' => (int) env('LOCAL_TTS_MAX_RESPONSE_BYTES', 15 * 1024 * 1024),
        'default_voice' => 'am_michael',
        'delivery_profile' => 'professional_mc',
        'model_sha256' => '496dba118d1a58f5f3db2efc88dbdc216e0483fc89fe6e47ee1f2c53f18ad1e4',
    ],
];

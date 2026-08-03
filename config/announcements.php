<?php

return [
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
];

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
];

<?php

return [
    'auto_assign' => (bool) env('INQUIRY_AUTO_ASSIGN', true),

    'strategy' => env('INQUIRY_ASSIGNMENT_STRATEGY', 'round_robin'),

    'dashboard_cache_seconds' => (int) env(
        'INQUIRY_DASHBOARD_CACHE_SECONDS',
        60
    ),

    'reminder_lookahead_days' => (int) env(
        'INQUIRY_REMINDER_LOOKAHEAD_DAYS',
        30
    ),

    'uploads' => [
        'disk' => 'public',
        'directory' => 'attachments',
        'max_files' => 3,
        'max_size_kb' => 5120,
        'extensions' => [
            'pdf',
            'doc',
            'docx',
            'jpg',
            'jpeg',
            'png',
        ],
    ],

    'pagination' => [
        'default' => 15,
        'maximum' => 100,
    ],
];

<?php

return [
    'disk' => env('MEDIA_DISK', 's3'),

    'max_upload_size_mb' => 50,

    'audio_max_duration_sec' => 120,
    'video_max_duration_sec' => 30,

    'allowed_document_mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
    'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    'audio_mimes' => ['mp3', 'wav', 'm4a', 'ogg', 'webm'],
    'video_mimes' => ['mp4', 'mov', 'webm'],

    'storage_quotas_mb' => [
        'freemium' => 500,
        'basic' => 5120,
        'pro' => 20480,
        'premium' => 51200,
    ],

    'overage_price_per_gb' => 1.00,
];

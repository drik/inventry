<?php

return [
    'enabled' => env('AI_VISION_ENABLED', false),

    // Provider primaire et fallback
    'primary_provider' => env('AI_VISION_PRIMARY_PROVIDER', 'gemini'),
    'fallback_provider' => env('AI_VISION_FALLBACK_PROVIDER', 'openai'),
    'fallback_confidence_threshold' => (float) env('AI_VISION_FALLBACK_CONFIDENCE_THRESHOLD', 0.5),

    // Configuration Gemini (provider primaire)
    'gemini' => [
        'model' => env('GEMINI_VISION_MODEL', 'gemini-2.5-flash'),
        'max_tokens' => 1000,
    ],

    // Configuration OpenAI (provider fallback)
    'openai' => [
        'model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),
        'max_tokens' => 1000,
    ],

    'limits' => [
        // Les quotas quotidiens/mensuels sont gérés par le plan de souscription
        // via PlanLimitService (PlanFeature::MaxAiRequestsDaily / MaxAiRequestsMonthly)
        'max_image_size_kb' => (int) env('AI_VISION_MAX_IMAGE_SIZE_KB', 2048),
        'max_reference_images' => 8,
    ],
];

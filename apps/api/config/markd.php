<?php

return [
    // Optional: a separate AI service. Leave empty to skip it for now.
    'ai_url' => env('AI_SERVICE_URL', ''),

    // Business assumptions. Tune them after talking to real collectors.
    'platform_share_bps' => (int) env('PLATFORM_SHARE_BPS', 2500), // our cut of the collector's commission (25%)

    // A saver who misses this many days in a row is treated as having defaulted.
    'default_after_days' => (int) env('DEFAULT_AFTER_DAYS', 7),

    'referral' => [
        'saver_reward_kobo' => (int) env('REF_SAVER_KOBO', 50000),          // N500, flat
        'collector_reward_kobo' => (int) env('REF_COLLECTOR_KOBO', 200000), // N2,000, flat
        'saver_min_days' => 7,        // the friend must have 7 marked days
        'collector_min_active' => 10, // a new collector must have 10 active savers
    ],
];

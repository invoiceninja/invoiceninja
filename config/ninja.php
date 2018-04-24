<?php

return [

    'video_urls' => [
        'all' => env('NINJA_VIDEOS_URL', 'https://www.youtube.com/channel/UCXAHcBvhW05PDtWYIq7WDFA/videos'),
        'custom_design' => env('NINJA_VIDEOS_CUSTOM_DESIGN_URL', 'https://www.youtube.com/watch?v=pXQ6jgiHodc'),
        'getting_started' => env('NINJA_VIDEOS_GETTING_STARTED_URL', 'https://www.youtube.com/watch?v=i7fqfi5HWeo'),
    ],

    // invoice locking feature
    'lock_sent_invoices' => env('LOCK_SENT_INVOICES'),

    // Marketing links
    'time_tracker_web_url' => env('TIME_TRACKER_WEB_URL', 'https://www.invoiceninja.com/time-tracker'),

    // Hosted plan coupons
    'coupon_50_off' => env('COUPON_50_OFF', false),
    'coupon_75_off' => env('COUPON_75_OFF', false),
    'coupon_free_year' => env('COUPON_FREE_YEAR', false),

    // data services
    'exchange_rates_url' => env('EXCHANGE_RATES_URL', 'https://api.fixer.io/latest'),
    'exchange_rates_base' => env('EXCHANGE_RATES_BASE', 'EUR'),

    // privacy policy
    'privacy_policy_url' => env('PRIVACY_POLICY_URL', ''),

    // Google maps
    'google_maps_enabled' => env('GOOGLE_MAPS_ENABLED', true),
    'google_maps_api_key' => env('GOOGLE_MAPS_API_KEY', ''),

    // Voice commands
    'voice_commands' => [
        'app_id' => env('MSBOT_LUIS_APP_ID', 'ea1cda29-5994-47c4-8c25-2b58ae7ae7a8'),
        'subscription_key' => env('MSBOT_LUIS_SUBSCRIPTION_KEY'),
    ],

];

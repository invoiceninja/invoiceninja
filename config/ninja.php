<?php

return [

    // invoice locking feature
    'lock_sent_invoices' => env('LOCK_SENT_INVOICES'),

    // Marketing links
    'time_tracker_web_url' => env('TIME_TRACKER_WEB_URL', 'https://www.invoiceninja.com/time-tracker'),

    // Hosted plan coupons
    'coupon_50_off' => env('COUPON_50_OFF', false),
    'coupon_75_off' => env('COUPON_75_OFF', false),
    'coupon_free_year' => env('COUPON_FREE_YEAR', false),

];

<?php

/**
 * GLOBAL CONSTANTS ONLY
 *
 * Class constants to be assigned and accessed statically via
 * their model ie, Invoice::STATUS_DEFAULT
 *
 */


define('BANK_LIBRARY_OFX', 1);
define('RANDOM_KEY_LENGTH', 32); //63340286662973277706162286946811886609896461828096 combinations


$cachedTables = [
        'currencies' => 'App\Models\Currency',
 //       'sizes' => 'App\Models\Size',
        'industries' => 'App\Models\Industry',
 //       'timezones' => 'App\Models\Timezone',
 //       'dateFormats' => 'App\Models\DateFormat',
 //       'datetimeFormats' => 'App\Models\DatetimeFormat',
        'languages' => 'App\Models\Language',
        'paymentTypes' => 'App\Models\PaymentType',
        'countries' => 'App\Models\Country',
//        'invoiceDesigns' => 'App\Models\InvoiceDesign',
//        'invoiceStatus' => 'App\Models\InvoiceStatus',
//        'frequencies' => 'App\Models\Frequency',
//        'gateways' => 'App\Models\Gateway',
//        'gatewayTypes' => 'App\Models\GatewayType',
//        'fonts' => 'App\Models\Font',
//        'banks' => 'App\Models\Bank',
    ];

define('CACHED_TABLES', serialize($cachedTables));

define('GATEWAY_TYPE_CREDIT_CARD', 1);
define('GATEWAY_TYPE_BANK_TRANSFER', 2);
define('GATEWAY_TYPE_PAYPAL', 3);
define('GATEWAY_TYPE_BITCOIN', 4);
define('GATEWAY_TYPE_DWOLLA', 5);
define('GATEWAY_TYPE_CUSTOM1', 6);
define('GATEWAY_TYPE_ALIPAY', 7);
define('GATEWAY_TYPE_SOFORT', 8);
define('GATEWAY_TYPE_SEPA', 9);
define('GATEWAY_TYPE_GOCARDLESS', 10);
define('GATEWAY_TYPE_APPLE_PAY', 11);
define('GATEWAY_TYPE_CUSTOM2', 12);
define('GATEWAY_TYPE_CUSTOM3', 13);
define('GATEWAY_TYPE_TOKEN', 'token');


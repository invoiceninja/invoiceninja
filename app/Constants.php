<?php

/**
 * GLOBAL CONSTANTS ONLY
 *
 * Class constants to be assigned and accessed statically via
 * their model ie, Invoice::STATUS_DEFAULT
 *
 */


define('BANK_LIBRARY_OFX', 1);


$cached_tables = [
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

define('CACHED_TABLES', serialize($cached_tables));

define('CACHED_PAYMENT_TERMS', serialize(
	[
		[
			'num_days' => 0,
			'name' => '',
		],
		[
			'num_days' => 7,
			'name' => '',
		],
		[
			'num_days' => 10,
			'name' => '',
		],
		[
			'num_days' => 14,
			'name' => '',
		],
		[
			'num_days' => 15,
			'name' => '',
		],
		[
			'num_days' => 30,
			'name' => '',
		],
		[
			'num_days' => 60,
			'name' => '',
		],
		[
			'num_days' => 90,
			'name' => '',
		]
	]));

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


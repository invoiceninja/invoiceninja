<?php

if (! defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'Invoice Ninja'));
    define('CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME')));
    define('CONTACT_NAME', env('MAIL_FROM_NAME'));
    define('SITE_URL', env('APP_URL'));

    define('ENV_DEVELOPMENT', 'local');
    define('ENV_STAGING', 'staging');

    define('RECENTLY_VIEWED', 'recent_history');

    define('ENTITY_CLIENT', 'client');
    define('ENTITY_CONTACT', 'contact');
    define('ENTITY_INVOICE', 'invoice');
    define('ENTITY_DOCUMENT', 'document');
    define('ENTITY_INVOICE_ITEM', 'invoice_item');
    define('ENTITY_INVITATION', 'invitation');
    define('ENTITY_RECURRING_INVOICE', 'recurring_invoice');
    define('ENTITY_PAYMENT', 'payment');
    define('ENTITY_CREDIT', 'credit');
    define('ENTITY_QUOTE', 'quote');
    define('ENTITY_TASK', 'task');
    define('ENTITY_ACCOUNT_GATEWAY', 'account_gateway');
    define('ENTITY_USER', 'user');
    define('ENTITY_TOKEN', 'token');
    define('ENTITY_TAX_RATE', 'tax_rate');
    define('ENTITY_PRODUCT', 'product');
    define('ENTITY_ACTIVITY', 'activity');
    define('ENTITY_VENDOR', 'vendor');
    define('ENTITY_VENDOR_ACTIVITY', 'vendor_activity');
    define('ENTITY_EXPENSE', 'expense');
    define('ENTITY_PAYMENT_TERM', 'payment_term');
    define('ENTITY_EXPENSE_ACTIVITY', 'expense_activity');
    define('ENTITY_BANK_ACCOUNT', 'bank_account');
    define('ENTITY_BANK_SUBACCOUNT', 'bank_subaccount');
    define('ENTITY_EXPENSE_CATEGORY', 'expense_category');
    define('ENTITY_PROJECT', 'project');

    define('INVOICE_TYPE_STANDARD', 1);
    define('INVOICE_TYPE_QUOTE', 2);

    define('INVOICE_ITEM_TYPE_STANDARD', 1);
    define('INVOICE_ITEM_TYPE_TASK', 2);
    define('INVOICE_ITEM_TYPE_PENDING_GATEWAY_FEE', 3);
    define('INVOICE_ITEM_TYPE_PAID_GATEWAY_FEE', 4);

    define('PERSON_CONTACT', 'contact');
    define('PERSON_USER', 'user');
    define('PERSON_VENDOR_CONTACT', 'vendorcontact');

    define('BASIC_SETTINGS', 'basic_settings');
    define('ADVANCED_SETTINGS', 'advanced_settings');

    define('ACCOUNT_COMPANY_DETAILS', 'company_details');
    define('ACCOUNT_USER_DETAILS', 'user_details');
    define('ACCOUNT_LOCALIZATION', 'localization');
    define('ACCOUNT_NOTIFICATIONS', 'notifications');
    define('ACCOUNT_IMPORT_EXPORT', 'import_export');
    define('ACCOUNT_MANAGEMENT', 'account_management');
    define('ACCOUNT_PAYMENTS', 'online_payments');
    define('ACCOUNT_BANKS', 'bank_accounts');
    define('ACCOUNT_IMPORT_EXPENSES', 'import_expenses');
    define('ACCOUNT_MAP', 'import_map');
    define('ACCOUNT_EXPORT', 'export');
    define('ACCOUNT_TAX_RATES', 'tax_rates');
    define('ACCOUNT_PRODUCTS', 'products');
    define('ACCOUNT_ADVANCED_SETTINGS', 'advanced_settings');
    define('ACCOUNT_INVOICE_SETTINGS', 'invoice_settings');
    define('ACCOUNT_INVOICE_DESIGN', 'invoice_design');
    define('ACCOUNT_CLIENT_PORTAL', 'client_portal');
    define('ACCOUNT_EMAIL_SETTINGS', 'email_settings');
    define('ACCOUNT_REPORTS', 'reports');
    define('ACCOUNT_USER_MANAGEMENT', 'user_management');
    define('ACCOUNT_DATA_VISUALIZATIONS', 'data_visualizations');
    define('ACCOUNT_TEMPLATES_AND_REMINDERS', 'templates_and_reminders');
    define('ACCOUNT_API_TOKENS', 'api_tokens');
    define('ACCOUNT_CUSTOMIZE_DESIGN', 'customize_design');
    define('ACCOUNT_SYSTEM_SETTINGS', 'system_settings');
    define('ACCOUNT_PAYMENT_TERMS', 'payment_terms');

    define('ACTION_RESTORE', 'restore');
    define('ACTION_ARCHIVE', 'archive');
    define('ACTION_CLONE', 'clone');
    define('ACTION_CONVERT', 'convert');
    define('ACTION_DELETE', 'delete');

    define('ACTIVITY_TYPE_CREATE_CLIENT', 1);
    define('ACTIVITY_TYPE_ARCHIVE_CLIENT', 2);
    define('ACTIVITY_TYPE_DELETE_CLIENT', 3);
    define('ACTIVITY_TYPE_CREATE_INVOICE', 4);
    define('ACTIVITY_TYPE_UPDATE_INVOICE', 5);
    define('ACTIVITY_TYPE_EMAIL_INVOICE', 6);
    define('ACTIVITY_TYPE_VIEW_INVOICE', 7);
    define('ACTIVITY_TYPE_ARCHIVE_INVOICE', 8);
    define('ACTIVITY_TYPE_DELETE_INVOICE', 9);
    define('ACTIVITY_TYPE_CREATE_PAYMENT', 10);
    //define('ACTIVITY_TYPE_UPDATE_PAYMENT', 11);
    define('ACTIVITY_TYPE_ARCHIVE_PAYMENT', 12);
    define('ACTIVITY_TYPE_DELETE_PAYMENT', 13);
    define('ACTIVITY_TYPE_CREATE_CREDIT', 14);
    //define('ACTIVITY_TYPE_UPDATE_CREDIT', 15);
    define('ACTIVITY_TYPE_ARCHIVE_CREDIT', 16);
    define('ACTIVITY_TYPE_DELETE_CREDIT', 17);
    define('ACTIVITY_TYPE_CREATE_QUOTE', 18);
    define('ACTIVITY_TYPE_UPDATE_QUOTE', 19);
    define('ACTIVITY_TYPE_EMAIL_QUOTE', 20);
    define('ACTIVITY_TYPE_VIEW_QUOTE', 21);
    define('ACTIVITY_TYPE_ARCHIVE_QUOTE', 22);
    define('ACTIVITY_TYPE_DELETE_QUOTE', 23);
    define('ACTIVITY_TYPE_RESTORE_QUOTE', 24);
    define('ACTIVITY_TYPE_RESTORE_INVOICE', 25);
    define('ACTIVITY_TYPE_RESTORE_CLIENT', 26);
    define('ACTIVITY_TYPE_RESTORE_PAYMENT', 27);
    define('ACTIVITY_TYPE_RESTORE_CREDIT', 28);
    define('ACTIVITY_TYPE_APPROVE_QUOTE', 29);
    define('ACTIVITY_TYPE_CREATE_VENDOR', 30);
    define('ACTIVITY_TYPE_ARCHIVE_VENDOR', 31);
    define('ACTIVITY_TYPE_DELETE_VENDOR', 32);
    define('ACTIVITY_TYPE_RESTORE_VENDOR', 33);
    define('ACTIVITY_TYPE_CREATE_EXPENSE', 34);
    define('ACTIVITY_TYPE_ARCHIVE_EXPENSE', 35);
    define('ACTIVITY_TYPE_DELETE_EXPENSE', 36);
    define('ACTIVITY_TYPE_RESTORE_EXPENSE', 37);
    define('ACTIVITY_TYPE_VOIDED_PAYMENT', 39);
    define('ACTIVITY_TYPE_REFUNDED_PAYMENT', 40);
    define('ACTIVITY_TYPE_FAILED_PAYMENT', 41);
    define('ACTIVITY_TYPE_CREATE_TASK', 42);
    define('ACTIVITY_TYPE_UPDATE_TASK', 43);
    define('ACTIVITY_TYPE_ARCHIVE_TASK', 44);
    define('ACTIVITY_TYPE_DELETE_TASK', 45);
    define('ACTIVITY_TYPE_RESTORE_TASK', 46);
    define('ACTIVITY_TYPE_UPDATE_EXPENSE', 47);

    define('DEFAULT_INVOICE_NUMBER', '0001');
    define('RECENTLY_VIEWED_LIMIT', 20);
    define('LOGGED_ERROR_LIMIT', 100);
    define('RANDOM_KEY_LENGTH', 32);
    define('MAX_NUM_USERS', 20);
    define('MAX_IMPORT_ROWS', 5000);
    define('MAX_SUBDOMAIN_LENGTH', 30);
    define('MAX_IFRAME_URL_LENGTH', 250);
    define('MAX_LOGO_FILE_SIZE', 200); // KB
    define('MAX_FAILED_LOGINS', 10);
    define('MAX_INVOICE_ITEMS', env('MAX_INVOICE_ITEMS', 100));
    define('MAX_DOCUMENT_SIZE', env('MAX_DOCUMENT_SIZE', 10000)); // KB
    define('MAX_EMAIL_DOCUMENTS_SIZE', env('MAX_EMAIL_DOCUMENTS_SIZE', 10000)); // Total KB
    define('MAX_ZIP_DOCUMENTS_SIZE', env('MAX_EMAIL_DOCUMENTS_SIZE', 30000)); // Total KB (uncompressed)
    define('DOCUMENT_PREVIEW_SIZE', env('DOCUMENT_PREVIEW_SIZE', 300)); // pixels
    define('DEFAULT_FONT_SIZE', 9);
    define('DEFAULT_HEADER_FONT', 1); // Roboto
    define('DEFAULT_BODY_FONT', 1); // Roboto
    define('DEFAULT_SEND_RECURRING_HOUR', 8);

    define('IMPORT_CSV', 'CSV');
    define('IMPORT_JSON', 'JSON');
    define('IMPORT_FRESHBOOKS', 'FreshBooks');
    define('IMPORT_WAVE', 'Wave');
    define('IMPORT_RONIN', 'Ronin');
    define('IMPORT_HIVEAGE', 'Hiveage');
    define('IMPORT_ZOHO', 'Zoho');
    define('IMPORT_NUTCACHE', 'Nutcache');
    define('IMPORT_INVOICEABLE', 'Invoiceable');
    define('IMPORT_INVOICEPLANE', 'InvoicePlane');
    define('IMPORT_HARVEST', 'Harvest');

    define('MAX_NUM_CLIENTS', 100);
    define('MAX_NUM_CLIENTS_PRO', 20000);
    define('MAX_NUM_CLIENTS_LEGACY', 500);
    define('MAX_INVOICE_AMOUNT', 1000000000);
    define('LEGACY_CUTOFF', 57800);
    define('ERROR_DELAY', 3);

    define('MAX_NUM_VENDORS', 100);
    define('MAX_NUM_VENDORS_PRO', 20000);

    define('STATUS_ACTIVE', 'active');
    define('STATUS_ARCHIVED', 'archived');
    define('STATUS_DELETED', 'deleted');

    define('INVOICE_STATUS_DRAFT', 1);
    define('INVOICE_STATUS_SENT', 2);
    define('INVOICE_STATUS_VIEWED', 3);
    define('INVOICE_STATUS_APPROVED', 4);
    define('INVOICE_STATUS_PARTIAL', 5);
    define('INVOICE_STATUS_PAID', 6);
    define('INVOICE_STATUS_OVERDUE', -1);
    define('INVOICE_STATUS_UNPAID', -2);

    define('PAYMENT_STATUS_PENDING', 1);
    define('PAYMENT_STATUS_VOIDED', 2);
    define('PAYMENT_STATUS_FAILED', 3);
    define('PAYMENT_STATUS_COMPLETED', 4);
    define('PAYMENT_STATUS_PARTIALLY_REFUNDED', 5);
    define('PAYMENT_STATUS_REFUNDED', 6);

    define('TASK_STATUS_LOGGED', 1);
    define('TASK_STATUS_RUNNING', 2);
    define('TASK_STATUS_INVOICED', 3);
    define('TASK_STATUS_PAID', 4);

    define('EXPENSE_STATUS_LOGGED', 1);
    define('EXPENSE_STATUS_PENDING', 2);
    define('EXPENSE_STATUS_INVOICED', 3);
    define('EXPENSE_STATUS_BILLED', 4);
    define('EXPENSE_STATUS_PAID', 5);
    define('EXPENSE_STATUS_UNPAID', 6);

    define('CUSTOM_DESIGN1', 11);
    define('CUSTOM_DESIGN2', 12);
    define('CUSTOM_DESIGN3', 13);

    define('FREQUENCY_WEEKLY', 1);
    define('FREQUENCY_TWO_WEEKS', 2);
    define('FREQUENCY_FOUR_WEEKS', 3);
    define('FREQUENCY_MONTHLY', 4);
    define('FREQUENCY_TWO_MONTHS', 5);
    define('FREQUENCY_THREE_MONTHS', 6);
    define('FREQUENCY_SIX_MONTHS', 7);
    define('FREQUENCY_ANNUALLY', 8);

    define('SESSION_TIMEZONE', 'timezone');
    define('SESSION_CURRENCY', 'currency');
    define('SESSION_CURRENCY_DECORATOR', 'currency_decorator');
    define('SESSION_DATE_FORMAT', 'dateFormat');
    define('SESSION_DATE_PICKER_FORMAT', 'datePickerFormat');
    define('SESSION_DATETIME_FORMAT', 'datetimeFormat');
    define('SESSION_COUNTER', 'sessionCounter');
    define('SESSION_LOCALE', 'sessionLocale');
    define('SESSION_USER_ACCOUNTS', 'userAccounts');
    define('SESSION_REFERRAL_CODE', 'referralCode');
    define('SESSION_LEFT_SIDEBAR', 'showLeftSidebar');
    define('SESSION_RIGHT_SIDEBAR', 'showRightSidebar');
    define('SESSION_DB_SERVER', 'dbServer');

    define('SESSION_LAST_REQUEST_PAGE', 'SESSION_LAST_REQUEST_PAGE');
    define('SESSION_LAST_REQUEST_TIME', 'SESSION_LAST_REQUEST_TIME');

    define('CURRENCY_DOLLAR', 1);
    define('CURRENCY_EURO', 3);

    define('DEFAULT_TIMEZONE', 'US/Eastern');
    define('DEFAULT_COUNTRY', 840); // United Stated
    define('DEFAULT_CURRENCY', CURRENCY_DOLLAR);
    define('DEFAULT_LANGUAGE', 1); // English
    define('DEFAULT_DATE_FORMAT', 'M j, Y');
    define('DEFAULT_DATE_PICKER_FORMAT', 'M d, yyyy');
    define('DEFAULT_DATETIME_FORMAT', 'F j, Y g:i a');
    define('DEFAULT_DATETIME_MOMENT_FORMAT', 'MMM D, YYYY h:mm:ss a');
    define('DEFAULT_LOCALE', 'en');
    define('DEFAULT_MAP_ZOOM', 10);

    define('RESULT_SUCCESS', 'success');
    define('RESULT_FAILURE', 'failure');

    define('PAYMENT_LIBRARY_OMNIPAY', 1);
    define('PAYMENT_LIBRARY_PHP_PAYMENTS', 2);

    define('GATEWAY_AUTHORIZE_NET', 1);
    define('GATEWAY_EWAY', 4);
    define('GATEWAY_MOLLIE', 9);
    define('GATEWAY_PAYFAST', 13);
    define('GATEWAY_PAYPAL_EXPRESS', 17);
    define('GATEWAY_PAYPAL_PRO', 18);
    define('GATEWAY_SAGE_PAY_DIRECT', 20);
    define('GATEWAY_SAGE_PAY_SERVER', 21);
    define('GATEWAY_STRIPE', 23);
    define('GATEWAY_GOCARDLESS', 6);
    define('GATEWAY_TWO_CHECKOUT', 27);
    define('GATEWAY_BEANSTREAM', 29);
    define('GATEWAY_PSIGATE', 30);
    define('GATEWAY_MOOLAH', 31);
    define('GATEWAY_BITPAY', 42);
    define('GATEWAY_DWOLLA', 43);
    define('GATEWAY_CHECKOUT_COM', 47);
    define('GATEWAY_CYBERSOURCE', 49);
    define('GATEWAY_WEPAY', 60);
    define('GATEWAY_BRAINTREE', 61);
    define('GATEWAY_CUSTOM', 62);

    // The customer exists, but only as a local concept
    // The remote gateway doesn't understand the concept of customers
    define('CUSTOMER_REFERENCE_LOCAL', 'local');

    define('EVENT_CREATE_CLIENT', 1);
    define('EVENT_CREATE_INVOICE', 2);
    define('EVENT_CREATE_QUOTE', 3);
    define('EVENT_CREATE_PAYMENT', 4);
    define('EVENT_CREATE_VENDOR', 5);
    define('EVENT_UPDATE_QUOTE', 6);
    define('EVENT_DELETE_QUOTE', 7);
    define('EVENT_UPDATE_INVOICE', 8);
    define('EVENT_DELETE_INVOICE', 9);

    define('REQUESTED_PRO_PLAN', 'REQUESTED_PRO_PLAN');
    define('NINJA_ACCOUNT_KEY', env('NINJA_ACCOUNT_KEY', 'zg4ylmzDkdkPOT8yoKQw9LTWaoZJx79h'));
    define('NINJA_ACCOUNT_EMAIL', env('NINJA_ACCOUNT_EMAIL', 'contact@invoiceninja.com'));
    define('NINJA_LICENSE_ACCOUNT_KEY', 'AsFmBAeLXF0IKf7tmi0eiyZfmWW9hxMT');
    define('NINJA_GATEWAY_ID', GATEWAY_STRIPE);
    define('NINJA_GATEWAY_CONFIG', 'NINJA_GATEWAY_CONFIG');
    define('NINJA_WEB_URL', env('NINJA_WEB_URL', 'https://www.invoiceninja.com'));
    define('NINJA_APP_URL', env('NINJA_APP_URL', 'https://app.invoiceninja.com'));
    define('NINJA_DOCS_URL', env('NINJA_DOCS_URL', 'http://docs.invoiceninja.com/en/latest'));
    define('NINJA_DATE', '2000-01-01');
    define('NINJA_VERSION', '3.4.2' . env('NINJA_VERSION_SUFFIX'));

    define('SOCIAL_LINK_FACEBOOK', env('SOCIAL_LINK_FACEBOOK', 'https://www.facebook.com/invoiceninja'));
    define('SOCIAL_LINK_TWITTER', env('SOCIAL_LINK_TWITTER', 'https://twitter.com/invoiceninja'));
    define('SOCIAL_LINK_GITHUB', env('SOCIAL_LINK_GITHUB', 'https://github.com/invoiceninja/invoiceninja/'));

    define('NINJA_FORUM_URL', env('NINJA_FORUM_URL', 'https://www.invoiceninja.com/forums/forum/support/'));
    define('NINJA_CONTACT_URL', env('NINJA_CONTACT_URL', 'https://www.invoiceninja.com/contact/'));
    define('NINJA_FROM_EMAIL', env('NINJA_FROM_EMAIL', 'maildelivery@invoiceninja.com'));
    define('NINJA_IOS_APP_URL', 'https://itunes.apple.com/WebObjects/MZStore.woa/wa/viewSoftware?id=1220337560&mt=8');
    define('NINJA_ANDROID_APP_URL', 'https://play.google.com/store/apps/details?id=com.invoiceninja.invoiceninja');
    define('RELEASES_URL', env('RELEASES_URL', 'https://trello.com/b/63BbiVVe/invoice-ninja'));
    define('ZAPIER_URL', env('ZAPIER_URL', 'https://zapier.com/zapbook/invoice-ninja'));
    define('OUTDATE_BROWSER_URL', env('OUTDATE_BROWSER_URL', 'http://browsehappy.com/'));
    define('PDFMAKE_DOCS', env('PDFMAKE_DOCS', 'http://pdfmake.org/playground.html'));
    define('PHANTOMJS_CLOUD', env('PHANTOMJS_CLOUD', 'http://api.phantomjscloud.com/api/browser/v2/'));
    define('PHP_DATE_FORMATS', env('PHP_DATE_FORMATS', 'http://php.net/manual/en/function.date.php'));
    define('REFERRAL_PROGRAM_URL', env('REFERRAL_PROGRAM_URL', 'https://www.invoiceninja.com/referral-program/'));
    define('EMAIL_MARKUP_URL', env('EMAIL_MARKUP_URL', 'https://developers.google.com/gmail/markup'));
    define('OFX_HOME_URL', env('OFX_HOME_URL', 'http://www.ofxhome.com/index.php/home/directory/all'));
    define('GOOGLE_ANALYITCS_URL', env('GOOGLE_ANALYITCS_URL', 'https://www.google-analytics.com/collect'));
    define('TRANSIFEX_URL', env('TRANSIFEX_URL', 'https://www.transifex.com/invoice-ninja/invoice-ninja'));
    define('IP_LOOKUP_URL', env('IP_LOOKUP_URL', 'http://whatismyipaddress.com/ip/'));
    define('CHROME_PDF_HELP_URL', 'https://support.google.com/chrome/answer/6213030?hl=en');
    define('FIREFOX_PDF_HELP_URL', 'https://support.mozilla.org/en-US/kb/view-pdf-files-firefox');

    define('MSBOT_LOGIN_URL', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    define('MSBOT_LUIS_URL', 'https://westus.api.cognitive.microsoft.com/luis/v2.0/apps');
    define('SKYPE_API_URL', 'https://apis.skype.com/v3');
    define('MSBOT_STATE_URL', 'https://state.botframework.com/v3');
    define('INVOICEPLANE_IMPORT', 'https://github.com/turbo124/Plane2Ninja');

    define('BOT_PLATFORM_WEB_APP', 'WebApp');
    define('BOT_PLATFORM_SKYPE', 'Skype');

    define('BLANK_IMAGE', 'data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');

    define('DB_NINJA_LOOKUP', 'db-ninja-0');
    define('DB_NINJA_1', 'db-ninja-1');
    define('DB_NINJA_2', 'db-ninja-2');

    define('COUNT_FREE_DESIGNS', 4);
    define('PRODUCT_ONE_CLICK_INSTALL', 1);
    define('PRODUCT_INVOICE_DESIGNS', 2);
    define('PRODUCT_WHITE_LABEL', 3);
    define('PRODUCT_SELF_HOST', 4);
    define('WHITE_LABEL_AFFILIATE_KEY', '92D2J5');
    define('INVOICE_DESIGNS_AFFILIATE_KEY', 'T3RS74');
    define('SELF_HOST_AFFILIATE_KEY', '8S69AD');

    define('PLAN_PRICE_PRO_MONTHLY', env('PLAN_PRICE_PRO_MONTHLY', 8));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_2', env('PLAN_PRICE_ENTERPRISE_MONTHLY_2', 12));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_5', env('PLAN_PRICE_ENTERPRISE_MONTHLY_5', 18));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_10', env('PLAN_PRICE_ENTERPRISE_MONTHLY_10', 24));
    define('PLAN_PRICE_ENTERPRISE_MONTHLY_20', env('PLAN_PRICE_ENTERPRISE_MONTHLY_20', 36));
    define('WHITE_LABEL_PRICE', env('WHITE_LABEL_PRICE', 20));
    define('INVOICE_DESIGNS_PRICE', env('INVOICE_DESIGNS_PRICE', 10));

    define('USER_TYPE_SELF_HOST', 'SELF_HOST');
    define('USER_TYPE_CLOUD_HOST', 'CLOUD_HOST');
    define('NEW_VERSION_AVAILABLE', 'NEW_VERSION_AVAILABLE');

    define('TEST_USERNAME', env('TEST_USERNAME', 'user@example.com'));
    define('TEST_PASSWORD', 'password');
    define('API_SECRET', 'API_SECRET');
    define('DEFAULT_API_PAGE_SIZE', 15);
    define('MAX_API_PAGE_SIZE', 500);

    define('IOS_DEVICE', env('IOS_DEVICE', ''));
    define('ANDROID_DEVICE', env('ANDROID_DEVICE', ''));

    define('TOKEN_BILLING_DISABLED', 1);
    define('TOKEN_BILLING_OPT_IN', 2);
    define('TOKEN_BILLING_OPT_OUT', 3);
    define('TOKEN_BILLING_ALWAYS', 4);

    define('PAYMENT_TYPE_CREDIT', 1);
    define('PAYMENT_TYPE_ACH', 5);
    define('PAYMENT_TYPE_VISA', 6);
    define('PAYMENT_TYPE_MASTERCARD', 7);
    define('PAYMENT_TYPE_AMERICAN_EXPRESS', 8);
    define('PAYMENT_TYPE_DISCOVER', 9);
    define('PAYMENT_TYPE_DINERS', 10);
    define('PAYMENT_TYPE_EUROCARD', 11);
    define('PAYMENT_TYPE_NOVA', 12);
    define('PAYMENT_TYPE_CREDIT_CARD_OTHER', 13);
    define('PAYMENT_TYPE_PAYPAL', 14);
    define('PAYMENT_TYPE_CARTE_BLANCHE', 17);
    define('PAYMENT_TYPE_UNIONPAY', 18);
    define('PAYMENT_TYPE_JCB', 19);
    define('PAYMENT_TYPE_LASER', 20);
    define('PAYMENT_TYPE_MAESTRO', 21);
    define('PAYMENT_TYPE_SOLO', 22);
    define('PAYMENT_TYPE_SWITCH', 23);

    define('PAYMENT_METHOD_STATUS_NEW', 'new');
    define('PAYMENT_METHOD_STATUS_VERIFICATION_FAILED', 'verification_failed');
    define('PAYMENT_METHOD_STATUS_VERIFIED', 'verified');

    define('GATEWAY_TYPE_CREDIT_CARD', 1);
    define('GATEWAY_TYPE_BANK_TRANSFER', 2);
    define('GATEWAY_TYPE_PAYPAL', 3);
    define('GATEWAY_TYPE_BITCOIN', 4);
    define('GATEWAY_TYPE_DWOLLA', 5);
    define('GATEWAY_TYPE_CUSTOM', 6);
    define('GATEWAY_TYPE_TOKEN', 'token');

    define('REMINDER1', 'reminder1');
    define('REMINDER2', 'reminder2');
    define('REMINDER3', 'reminder3');

    define('RESET_FREQUENCY_DAILY', 1);
    define('RESET_FREQUENCY_WEEKLY', 2);
    define('RESET_FREQUENCY_MONTHLY', 3);
    define('RESET_FREQUENCY_QUATERLY', 4);
    define('RESET_FREQUENCY_YEARLY', 5);

    define('REMINDER_DIRECTION_AFTER', 1);
    define('REMINDER_DIRECTION_BEFORE', 2);

    define('REMINDER_FIELD_DUE_DATE', 1);
    define('REMINDER_FIELD_INVOICE_DATE', 2);

    define('FILTER_INVOICE_DATE', 'invoice_date');
    define('FILTER_PAYMENT_DATE', 'payment_date');

    define('SOCIAL_GOOGLE', 'Google');
    define('SOCIAL_FACEBOOK', 'Facebook');
    define('SOCIAL_GITHUB', 'GitHub');
    define('SOCIAL_LINKEDIN', 'LinkedIn');

    define('USER_STATE_ACTIVE', 'active');
    define('USER_STATE_PENDING', 'pending');
    define('USER_STATE_DISABLED', 'disabled');
    define('USER_STATE_ADMIN', 'admin');
    define('USER_STATE_OWNER', 'owner');

    define('API_SERIALIZER_ARRAY', 'array');
    define('API_SERIALIZER_JSON', 'json');

    define('EMAIL_DESIGN_PLAIN', 1);
    define('EMAIL_DESIGN_LIGHT', 2);
    define('EMAIL_DESIGN_DARK', 3);

    define('BANK_LIBRARY_OFX', 1);

    define('CURRENCY_DECORATOR_CODE', 'code');
    define('CURRENCY_DECORATOR_SYMBOL', 'symbol');
    define('CURRENCY_DECORATOR_NONE', 'none');

    define('RESELLER_REVENUE_SHARE', 'A');
    define('RESELLER_ACCOUNT_COUNT', 'B');

    define('AUTO_BILL_OFF', 1);
    define('AUTO_BILL_OPT_IN', 2);
    define('AUTO_BILL_OPT_OUT', 3);
    define('AUTO_BILL_ALWAYS', 4);

    // These must be lowercase
    define('PLAN_FREE', 'free');
    define('PLAN_PRO', 'pro');
    define('PLAN_ENTERPRISE', 'enterprise');
    define('PLAN_WHITE_LABEL', 'white_label');
    define('PLAN_TERM_MONTHLY', 'month');
    define('PLAN_TERM_YEARLY', 'year');

    // Pro
    define('FEATURE_CUSTOMIZE_INVOICE_DESIGN', 'customize_invoice_design');
    define('FEATURE_REMOVE_CREATED_BY', 'remove_created_by');
    define('FEATURE_DIFFERENT_DESIGNS', 'different_designs');
    define('FEATURE_EMAIL_TEMPLATES_REMINDERS', 'email_templates_reminders');
    define('FEATURE_INVOICE_SETTINGS', 'invoice_settings');
    define('FEATURE_CUSTOM_EMAILS', 'custom_emails');
    define('FEATURE_PDF_ATTACHMENT', 'pdf_attachment');
    define('FEATURE_MORE_INVOICE_DESIGNS', 'more_invoice_designs');
    define('FEATURE_QUOTES', 'quotes');
    define('FEATURE_TASKS', 'tasks');
    define('FEATURE_EXPENSES', 'expenses');
    define('FEATURE_REPORTS', 'reports');
    define('FEATURE_BUY_NOW_BUTTONS', 'buy_now_buttons');
    define('FEATURE_API', 'api');
    define('FEATURE_CLIENT_PORTAL_PASSWORD', 'client_portal_password');
    define('FEATURE_CUSTOM_URL', 'custom_url');

    define('FEATURE_MORE_CLIENTS', 'more_clients'); // No trial allowed

    // Whitelabel
    define('FEATURE_WHITE_LABEL', 'feature_white_label');

    // Enterprise
    define('FEATURE_DOCUMENTS', 'documents');

    // No Trial allowed
    define('FEATURE_USERS', 'users'); // Grandfathered for old Pro users
    define('FEATURE_USER_PERMISSIONS', 'user_permissions');

    // Pro users who started paying on or before this date will be able to manage users
    define('PRO_USERS_GRANDFATHER_DEADLINE', '2016-06-04');
    define('EXTRAS_GRANDFATHER_COMPANY_ID', 35089);

    // WePay
    define('WEPAY_PRODUCTION', 'production');
    define('WEPAY_STAGE', 'stage');
    define('WEPAY_CLIENT_ID', env('WEPAY_CLIENT_ID'));
    define('WEPAY_CLIENT_SECRET', env('WEPAY_CLIENT_SECRET'));
    define('WEPAY_AUTO_UPDATE', env('WEPAY_AUTO_UPDATE', false));
    define('WEPAY_ENVIRONMENT', env('WEPAY_ENVIRONMENT', WEPAY_PRODUCTION));
    define('WEPAY_ENABLE_CANADA', env('WEPAY_ENABLE_CANADA', false));
    define('WEPAY_THEME', env('WEPAY_THEME', '{"name":"Invoice Ninja","primary_color":"0b4d78","secondary_color":"0b4d78","background_color":"f8f8f8","button_color":"33b753"}'));

    define('SKYPE_CARD_RECEIPT', 'message/card.receipt');
    define('SKYPE_CARD_CAROUSEL', 'message/card.carousel');
    define('SKYPE_CARD_HERO', '');

    define('BOT_STATE_GET_EMAIL', 'get_email');
    define('BOT_STATE_GET_CODE', 'get_code');
    define('BOT_STATE_READY', 'ready');
    define('SIMILAR_MIN_THRESHOLD', 50);

    // https://docs.botframework.com/en-us/csharp/builder/sdkreference/attachments.html
    define('SKYPE_BUTTON_OPEN_URL', 'openUrl');
    define('SKYPE_BUTTON_IM_BACK', 'imBack');
    define('SKYPE_BUTTON_POST_BACK', 'postBack');
    define('SKYPE_BUTTON_CALL', 'call'); // "tel:123123123123"
    define('SKYPE_BUTTON_PLAY_AUDIO', 'playAudio');
    define('SKYPE_BUTTON_PLAY_VIDEO', 'playVideo');
    define('SKYPE_BUTTON_SHOW_IMAGE', 'showImage');
    define('SKYPE_BUTTON_DOWNLOAD_FILE', 'downloadFile');

    define('INVOICE_FIELDS_CLIENT', 'client_fields');
    define('INVOICE_FIELDS_INVOICE', 'invoice_fields');
    define('INVOICE_FIELDS_ACCOUNT', 'account_fields');

    $creditCards = [
                1 => ['card' => 'images/credit_cards/Test-Visa-Icon.png', 'text' => 'Visa'],
                2 => ['card' => 'images/credit_cards/Test-MasterCard-Icon.png', 'text' => 'Master Card'],
                4 => ['card' => 'images/credit_cards/Test-AmericanExpress-Icon.png', 'text' => 'American Express'],
                8 => ['card' => 'images/credit_cards/Test-Diners-Icon.png', 'text' => 'Diners'],
                16 => ['card' => 'images/credit_cards/Test-Discover-Icon.png', 'text' => 'Discover'],
            ];
    define('CREDIT_CARDS', serialize($creditCards));

    $cachedTables = [
        'currencies' => 'App\Models\Currency',
        'sizes' => 'App\Models\Size',
        'industries' => 'App\Models\Industry',
        'timezones' => 'App\Models\Timezone',
        'dateFormats' => 'App\Models\DateFormat',
        'datetimeFormats' => 'App\Models\DatetimeFormat',
        'languages' => 'App\Models\Language',
        'paymentTerms' => 'App\Models\PaymentTerm',
        'paymentTypes' => 'App\Models\PaymentType',
        'countries' => 'App\Models\Country',
        'invoiceDesigns' => 'App\Models\InvoiceDesign',
        'invoiceStatus' => 'App\Models\InvoiceStatus',
        'frequencies' => 'App\Models\Frequency',
        'gateways' => 'App\Models\Gateway',
        'gatewayTypes' => 'App\Models\GatewayType',
        'fonts' => 'App\Models\Font',
        'banks' => 'App\Models\Bank',
    ];
    define('CACHED_TABLES', serialize($cachedTables));

    // TODO remove these translation functions
    function uctrans($text)
    {
        return ucwords(trans($text));
    }

    // optional trans: only return the string if it's translated
    function otrans($text)
    {
        $locale = Session::get(SESSION_LOCALE);

        if ($locale == 'en') {
            return trans($text);
        } else {
            $string = trans($text);
            $english = trans($text, [], 'en');

            return $string != $english ? $string : '';
        }
    }

    // include modules in translations
    function mtrans($entityType, $text = false)
    {
        if (! $text) {
            $text = $entityType;
        }

        // check if this has been translated in a module language file
        if (! Utils::isNinjaProd() && $module = Module::find($entityType)) {
            $key = "{$module->getLowerName()}::texts.{$text}";
            $value = trans($key);
            if ($key != $value) {
                return $value;
            }
        }

        return trans("texts.{$text}");
    }
}

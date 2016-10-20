{!! Former::populateField(GATEWAY_STRIPE . '_apiKey', env('STRIPE_TEST_SECRET_KEY')) !!}
{!! Former::populateField('publishable_key', env('STRIPE_TEST_PUBLISHABLE_KEY')) !!}
{!! Former::populateField('enable_ach', 1) !!}
{!! Former::populateField('plaid_client_id', env('PLAID_TEST_CLIENT_ID')) !!}
{!! Former::populateField('plaid_secret', env('PLAID_TEST_SECRET')) !!}
{!! Former::populateField('plaid_public_key', env('PLAID_TEST_PUBLIC_KEY')) !!}

{!! Former::populateField(GATEWAY_PAYPAL_EXPRESS . '_username', env('PAYPAL_TEST_USERNAME')) !!}
{!! Former::populateField(GATEWAY_PAYPAL_EXPRESS . '_password', env('PAYPAL_TEST_PASSWORD')) !!}
{!! Former::populateField(GATEWAY_PAYPAL_EXPRESS . '_signature', env('PAYPAL_TEST_SIGNATURE')) !!}
{!! Former::populateField(GATEWAY_PAYPAL_EXPRESS . '_testMode', 1) !!}

{!! Former::populateField(GATEWAY_DWOLLA . '_destinationId', env('DWOLLA_TEST_DESTINATION_ID')) !!}

{!! Former::populateField(GATEWAY_BITPAY . '_testMode', 1) !!}
{!! Former::populateField(GATEWAY_BITPAY . '_apiKey', env('BITPAY_TEST_API_KEY')) !!}

{!! Former::populateField(GATEWAY_TWO_CHECKOUT . '_secretWord', env('TWO_CHECKOUT_SECRET_WORD')) !!}
{!! Former::populateField(GATEWAY_TWO_CHECKOUT . '_accountNumber', env('TWO_CHECKOUT_ACCOUNT_NUMBER')) !!}
{!! Former::populateField(GATEWAY_TWO_CHECKOUT . '_testMode', 1) !!}

{!! Former::populateField(GATEWAY_MOLLIE . '_apiKey', env('MOLLIE_TEST_API_KEY')) !!}

{!! Former::populateField(GATEWAY_AUTHORIZE_NET . '_apiLoginId', env('AUTHORIZE_NET_TEST_API_LOGIN_ID')) !!}
{!! Former::populateField(GATEWAY_AUTHORIZE_NET . '_transactionKey', env('AUTHORIZE_NET_TEST_TRANSACTION_KEY')) !!}
{!! Former::populateField(GATEWAY_AUTHORIZE_NET . '_secretKey', env('AUTHORIZE_NET_TEST_SECRET_KEY')) !!}
{!! Former::populateField(GATEWAY_AUTHORIZE_NET . '_testMode', 1) !!}
{!! Former::populateField(GATEWAY_AUTHORIZE_NET . '_developerMode', 1) !!}

{!! Former::populateField(GATEWAY_CHECKOUT_COM . '_publicApiKey', env('CHECKOUT_COM_TEST_PUBLIC_API_KEY')) !!}
{!! Former::populateField(GATEWAY_CHECKOUT_COM . '_secretApiKey', env('CHECKOUT_COM_TEST_SECRET_API_KEY')) !!}
{!! Former::populateField(GATEWAY_CHECKOUT_COM . '_testMode', 1) !!}

{!! Former::populateField(GATEWAY_CYBERSOURCE . '_accessKey', env('CYBERSOURCE_TEST_ACCESS_KEY')) !!}
{!! Former::populateField(GATEWAY_CYBERSOURCE . '_secretKey', env('CYBERSOURCE_TEST_SECRET_KEY')) !!}
{!! Former::populateField(GATEWAY_CYBERSOURCE . '_profileId', env('CYBERSOURCE_TEST_PROFILE_ID')) !!}
{!! Former::populateField(GATEWAY_CYBERSOURCE . '_testMode', 1) !!}

{!! Former::populateField(GATEWAY_EWAY . '_apiKey', env('EWAY_TEST_API_KEY')) !!}
{!! Former::populateField(GATEWAY_EWAY . '_password', env('EWAY_TEST_PASSWORD')) !!}
{!! Former::populateField(GATEWAY_EWAY . '_testMode', 1) !!}

{!! Former::populateField(GATEWAY_BRAINTREE . '_privateKey', env('BRAINTREE_TEST_PRIVATE_KEY')) !!}
{!! Former::populateField(GATEWAY_BRAINTREE . '_publicKey', env('BRAINTREE_TEST_PUBLIC_KEY')) !!}
{!! Former::populateField(GATEWAY_BRAINTREE . '_merchantId', env('BRAINTREE_TEST_MERCHANT_ID')) !!}
{!! Former::populateField(GATEWAY_BRAINTREE . '_testMode', 1) !!}
{!! Former::populateField('enable_paypal', 1) !!}

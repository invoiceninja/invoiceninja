<?php

use App\Models\Gateway;
use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {

    public function up(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        if (! Gateway::find(67)) {
            $fields = new \stdClass;
            $fields->partnerId = '';
            $fields->vposId = '';
            $fields->paywarePublicKey = '';
            $fields->testMode = false;
            $fields->timeToLive = 600;

            $gateway = new Gateway();
            $gateway->id = 67;
            $gateway->name = 'payware';
            $gateway->key = 'b0a6294fca4488c2bab58f3e11e3c623';
            $gateway->provider = 'Payware';
            $gateway->is_offsite = false;
            $gateway->fields = \json_encode($fields);
            $gateway->visible = true;
            $gateway->sort_order = 29;
            $gateway->site_url = 'https://payware.eu';
            $gateway->default_gateway_type_id = 30;
            $gateway->save();
        }

        // Generic Mobile Payment type for mobile-initiated payments (payware and any future
        // mobile gateway). Distinct from existing brand-specific entries (e.g. INSTANT_BANK_PAY).
        if (! PaymentType::find(PaymentType::MOBILE_PAYMENT)) {
            $paymentType = new PaymentType();
            $paymentType->id = PaymentType::MOBILE_PAYMENT;
            $paymentType->name = 'Mobile Payment';
            $paymentType->gateway_type_id = 30;
            $paymentType->save();
        }

        \Illuminate\Database\Eloquent\Model::reguard();

    }

    public function down(): void
    {
        //
    }
};

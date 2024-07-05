<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Flow2;

use App\Exceptions\PaymentFailed;
use App\Utils\Traits\WithSecureContext;
use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Models\InvoiceInvitation;
use App\Services\ClientPortal\InstantPayment;
use App\Services\ClientPortal\LivewireInstantPayment;

class ProcessPayment extends Component
{
    use WithSecureContext;

    private string $component_view = '';

    private array $payment_data_payload = [];

    public $isLoading = true;

    public function mount()
    {

        MultiDB::setDb($this->getContext()['db']);

        $invitation = InvoiceInvitation::find($this->getContext()['invitation_id']);

        $data = [
            'company_gateway_id' => $this->getContext()['company_gateway_id'],
            'payment_method_id' => $this->getContext()['gateway_type_id'],
            'payable_invoices' => $this->getContext()['payable_invoices'],
            'signature' => isset($this->getContext()['signature']) ? $this->getContext()['signature'] : false,
            'signature_ip' => isset($this->getContext()['signature_ip']) ? $this->getContext()['signature_ip'] : false,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
            // 'hash' => false,
        ];

        $responder_data = (new LivewireInstantPayment($data))->run();

        $company_gateway = CompanyGateway::find($this->getContext()['company_gateway_id']);

        $this->component_view = '';

        if(!$responder_data['success']) {
            throw new PaymentFailed($responder_data['error'], 400);
        }

        $driver = $company_gateway
                ->driver($invitation->contact->client)
                ->setPaymentMethod($data['payment_method_id'])
                ->setPaymentHash($responder_data['payload']['ph']);

        $payment_data = $driver->processPaymentViewData($responder_data['payload']);
        $payment_data['client_secret'] = $payment_data['intent']->client_secret;

        unset($payment_data['intent']);

        $token_billing_string = 'true';

        if($company_gateway->token_billing == 'off' || $company_gateway->token_billing == 'optin') {
            $token_billing_string = 'false';
        }

        if (isset($data['pre_payment']) && $data['pre_payment'] == '1' && isset($data['is_recurring']) && $data['is_recurring'] == '1') {
            $token_billing_string = 'true';
        }

        $payment_data['token_billing_string'] = $token_billing_string;

        $this->payment_data_payload = $payment_data;
        // $this->payment_data_payload['company_gateway'] = $company_gateway;

        $this->payment_data_payload =
        [
            'stripe_account_id' => $this->payment_data_payload['company_gateway']->getConfigField('account_id'),
            'publishable_key' => $this->payment_data_payload['company_gateway']->getPublishableKey(),
            'require_postal_code' => $this->payment_data_payload['company_gateway']->require_postal_code,
            'gateway' => $this->payment_data_payload['gateway'],
            'client' => $this->payment_data_payload['client'],
            'payment_method_id' => $this->payment_data_payload['payment_method_id'],
            'token_billing_string' => $this->payment_data_payload['token_billing_string'],
            'tokens' => $this->payment_data_payload['tokens'],
            'client_secret' => $this->payment_data_payload['client_secret'],
            'payment_hash' => $this->payment_data_payload['payment_hash'],
            'total' => $this->payment_data_payload['total'],
            'invoices' => $this->payment_data_payload['invoices'],
            'amount_with_fee' => $this->payment_data_payload['amount_with_fee'],
            'pre_payment' => $this->payment_data_payload['pre_payment'],
            'is_recurring' => $this->payment_data_payload['is_recurring'],
            'company_gateway' => $this->payment_data_payload['company_gateway'],
        ];


        $this->isLoading = false;

    }

    public function render(): \Illuminate\Contracts\View\Factory|string|\Illuminate\View\View
    {
        if ($this->isLoading) {
            return <<<'HTML'
            <template></template>
        HTML;
        }

        return render('gateways.stripe.credit_card.livewire_pay', $this->payment_data_payload);
    }
}

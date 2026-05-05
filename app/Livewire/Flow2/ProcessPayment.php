<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Flow2;

use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Exceptions\PaymentFailed;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\WithSecureContext;
use App\Services\ClientPortal\LivewireInstantPayment;
use Livewire\Attributes\Lazy;

class ProcessPayment extends Component
{
    use WithSecureContext;

    private ?string $payment_view;

    private array $payment_data_payload = [];

    public $_key;

    public function mount()
    {

        MultiDB::setDb($this->getContext($this->_key)['db']);

        $_context = $this->getContext($this->_key);

        $data = [
            'company_gateway_id' => $_context['company_gateway_id'],
            'payment_method_id' => $_context['gateway_type_id'],
            'payable_invoices' => $_context['payable_invoices'],
            'signature' => $_context['signature'] ?? false,
            'signature_ip' => $_context['signature_ip'] ?? false,
            'pre_payment' => false,
            'frequency_id' => false,
            'remaining_cycles' => false,
            'is_recurring' => false,
            // 'hash' => false,
        ];

        $responder_data = (new LivewireInstantPayment($data))->run();

        if (!$responder_data['success']) {
            throw new PaymentFailed($responder_data['error'], 400);
        }

        if (isset($responder_data['payload']['total']['fee_total'])) {

            $gateway_fee = data_get($responder_data, 'payload.total.fee_total', false);
            $amount = data_get($responder_data, 'payload.total.amount_with_fee', 0);

            $this->bulkSetContext($this->_key, [
                'amount' => $amount,
                'gateway_fee' => $gateway_fee,
            ]);

            $this->dispatch('payment-view-rendered');
        }


        if (isset($responder_data['component']) && $responder_data['component'] == 'CreditPaymentComponent') {
            $this->payment_view = $responder_data['view'];
            $this->payment_data_payload = $responder_data['payload'];
        } else {
            
            if (! $responder_data['payload']['company_gateway']) {
                throw new PaymentFailed('Gateway no longer available', 400);
            }

            $driver = $responder_data['payload']['company_gateway']
                // ->driver($invitation->contact->client) // @phpstan-ignore-line
                ->driver($responder_data['payload']['client']) // @phpstan-ignore-line
                ->setPaymentMethod($data['payment_method_id'])
                ->setPaymentHash($responder_data['payload']['ph']);

            $this->payment_data_payload = $driver->processPaymentViewData($responder_data['payload']);

            $this->payment_view = $driver->livewirePaymentView(
                $this->payment_data_payload,
            );
        }

    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="rounded-lg border bg-white shadow-sm px-4 py-5 sm:px-6 flex items-center justify-center min-h-[160px]">
            <svg class="animate-spin h-8 w-8 text-primary" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>   
        </div>
        HTML;
    }

    public function render()
    {
        return render($this->payment_view, $this->payment_data_payload);
    }

    public function exception($e, $stopPropagation)
    {

        app('sentry')->captureException($e);

        $errors = session()->get('errors', new \Illuminate\Support\ViewErrorBag());

        $bag = new \Illuminate\Support\MessageBag();
        $bag->add('gateway_error', $e->getMessage());
        session()->put('errors', $errors->put('default', $bag));

        $invoice_id = $this->getContext($this->_key)['payable_invoices'][0]['invoice_id'];
        $this->redirectRoute('client.invoice.show', ['invoice' => $invoice_id]);
        $stopPropagation();

    }

}

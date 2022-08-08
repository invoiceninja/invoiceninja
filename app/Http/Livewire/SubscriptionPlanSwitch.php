<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

class SubscriptionPlanSwitch extends Component
{
    /**
     * @var RecurringInvoice
     */
    public $recurring_invoice;

    /**
     * @var Subscription
     */
    public $subscription;

    /**
     * @var ?float
     */
    public $amount;

    /**
     * @var Subscription
     */
    public $target;

    /**
     * @var ClientContact
     */
    public $contact;

    /**
     * @var array
     */
    public $methods = [];

    /**
     * @var string
     */
    public $total;

    /**
     * @var array
     */
    public $state = [
        'payment_initialised' => false,
        'show_loading_bar' => false,
        'invoice' => null,
        'company_gateway_id' => null,
        'payment_method_id' => null,
    ];

    /**
     * @var mixed|string
     */
    public $hash;

    public $company;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->total = $this->amount;

        $this->methods = $this->contact->client->service()->getPaymentMethods($this->amount);

        $this->hash = Str::uuid()->toString();
    }

    public function handleBeforePaymentEvents(): void
    {
        $this->state['show_loading_bar'] = true;

        $payment_required = $this->target->service()->changePlanPaymentCheck([
            'recurring_invoice' => $this->recurring_invoice,
            'subscription' => $this->subscription,
            'target' => $this->target,
            'hash' => $this->hash,
        ]);

        if ($payment_required) {
            $this->state['invoice'] = $this->target->service()->createChangePlanInvoice([
                'recurring_invoice' => $this->recurring_invoice,
                'subscription' => $this->subscription,
                'target' => $this->target,
                'hash' => $this->hash,
            ]);

            Cache::put($this->hash, [
                'subscription_id' => $this->target->id,
                'target_id' => $this->target->id,
                'recurring_invoice' => $this->recurring_invoice->id,
                'client_id' => $this->recurring_invoice->client->id,
                'invoice_id' => $this->state['invoice']->id,
                'context' => 'change_plan',
                now()->addMinutes(60), ]
                );

            $this->state['payment_initialised'] = true;
        } else {
            $this->handlePaymentNotRequired();
        }

        $this->emit('beforePaymentEventsCompleted');
    }

    /**
     * Middle method between selecting payment method &
     * submitting the from to the backend.
     *
     * @param $company_gateway_id
     * @param $gateway_type_id
     */
    public function handleMethodSelectingEvent($company_gateway_id, $gateway_type_id)
    {
        $this->state['company_gateway_id'] = $company_gateway_id;
        $this->state['payment_method_id'] = $gateway_type_id;

        $this->handleBeforePaymentEvents();
    }

    public function handlePaymentNotRequired()
    {
        return $this->target->service()->createChangePlanCredit([
            'recurring_invoice' => $this->recurring_invoice,
            'subscription' => $this->subscription,
            'target' => $this->target,
            'hash' => $this->hash,
        ]);
    }

    public function render()
    {
        return render('components.livewire.subscription-plan-switch');
    }
}

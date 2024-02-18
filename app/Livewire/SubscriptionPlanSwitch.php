<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Subscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Component;

class SubscriptionPlanSwitch extends Component
{
    /**
     * @var \App\Models\RecurringInvoice
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
    public ClientContact $contact;

    /**
     * @var array
     */
    public $methods = [];

    /**
     * @var string
     */
    public $total;

    public $hide_button = false;
    /**
     * @var array
     */
    public $state = [
        'payment_initialised' => false,
        'show_loading_bar' => false,
        'invoice' => null,
        'company_gateway_id' => null,
        'payment_method_id' => null,
        'show_rff' => false,
    ];

    /**
     * @var mixed|string
     */
    public $hash;

    public $company;

    public ?string $first_name;

    public ?string $last_name;

    public ?string $email;

    public function mount()
    {
        MultiDB::setDb($this->company->db);

        $this->total = $this->amount;

        $this->methods = $this->contact->client->service()->getPaymentMethods($this->amount);

        $this->hash = Str::uuid()->toString();

        $this->state['show_rff'] = auth()->guard('contact')->user()->showRff();

        $this->first_name = $this->contact->first_name;

        $this->last_name = $this->contact->last_name;

        $this->email = $this->contact->email;
    }

    public function handleRff()
    {
        $this->validate([
            'first_name' => ['required'],
            'last_name' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $this->contact->update([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
        ]);

        $this->state['show_rff'] = false;
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

            Cache::put(
                $this->hash,
                [
                'subscription_id' => $this->target->hashed_id,
                'target_id' => $this->target->hashed_id,
                'recurring_invoice' => $this->recurring_invoice->hashed_id,
                'client_id' => $this->recurring_invoice->client->hashed_id,
                'invoice_id' => $this->state['invoice']->hashed_id,
                'context' => 'change_plan',
                now()->addMinutes(60), ]
            );

            $this->state['payment_initialised'] = true;
        } else {
            $this->handlePaymentNotRequired();
        }

        $this->dispatch('beforePaymentEventsCompleted');
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
        $this->hide_button = true;

        $response =  $this->target->service()->createChangePlanCreditV2([
            'recurring_invoice' => $this->recurring_invoice,
            'subscription' => $this->subscription,
            'target' => $this->target,
            'hash' => $this->hash,
        ]);

        $this->hide_button = true;

        $this->dispatch('redirectRoute', ['route' => $response]);

    }

    public function render()
    {
        return render('components.livewire.subscription-plan-switch');
    }
}

<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use App\Models\ClientContact;
use App\Models\Subscription;
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

    public function mount()
    {
        // $this->total = $this->subscription->service()->getPriceBetweenSubscriptions($this->subscription, $this->target);

        $this->total = $this->amount;

        $this->methods = $this->contact->client->service()->getPaymentMethods(100);

        $this->hash = Str::uuid()->toString();
    }

    public function handleBeforePaymentEvents(): void
    {
        $this->state['show_loading_bar'] = true;

        $this->state['invoice'] = $this->subscription->service()->createChangePlanInvoice([
            'subscription' => $this->subscription,
            'target' => $this->target,
        ]);

        $this->state['payment_initialised'] = true;

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

    public function render()
    {
        return render('components.livewire.subscription-plan-switch');
    }
}

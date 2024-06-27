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

use Livewire\Component;
use App\Libraries\MultiDB;

class PaymentMethod extends Component
{
    public $invoice;

    public $context;

    public $variables;

    public function mount()
    {
        $this->invoice = $this->context['invoice'];
        $this->variables = $this->context['variables'];
    }

    public function render()
    {
        
        MultiDB::setDb($this->invoice->company->db);

        $methods = $this->invoice->client->service()->getPaymentMethods($this->invoice->balance);

        return render('components.livewire.payment_method-flow2', ['methods' => $methods, 'amount' => $this->invoice->balance]);
    }
}

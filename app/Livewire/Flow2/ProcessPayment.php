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
use App\Models\InvoiceInvitation;
use App\Services\ClientPortal\InstantPayment;

class ProcessPayment extends Component
{

    public $context;

    public function mount()
    {
    }

    public function render()
    {

        MultiDB::setDb($this->context['invoice']->company->db);

        $invitation = InvoiceInvitation::find($this->context['invitation_id']);

        $data = [
            'company_gateway_id' => $this->context['company_gateway_id'],
            'payment_method_id' => $this->context['gateway_type_id'],
            'payable_invoices' => [$this->context['payable_invoices']],
            'signature' => isset($this->context['signature']) ? $this->context['signature'] : false,
            'contact_first_name' => $invitation->contact->first_name ?? '',
            'contact_last_name' => $invitation->contact->last_name ?? '',
            'contact_email' => $invitation->contact->email ?? ''
        ];

        request()->replace($data);

        return (new InstantPayment(request()))->run();

        // return render($view->view, $view->data);
    }
}

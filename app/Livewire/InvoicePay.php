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

namespace App\Livewire;

use App\Livewire\Terms;
use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Livewire\Signature;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;

class InvoicePay extends Component
{
    public $invitation_id;

    public $db;

    public $settings;

    private $invite;

    private $variables;

    public $terms_accepted = false;
    
    public $signature_accepted = false;

    #[On('signature-captured')]
    public function signatureCaptured($base64)
    {
        nlog("signature captured");

        $this->signature_accepted = true;
        $this->invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id)->withoutRelations();
        $this->invite->signature_base64 = $base64;
        $this->invite->signature_date = now()->addSeconds($this->invite->contact->client->timezone_offset());
        $this->invite->save();
    
    }

    #[On('terms-accepted')]
    public function termsAccepted()
    {
        nlog("Terms accepted");
        $this->invite = \App\Models\InvoiceInvitation::withTrashed()->find($this->invitation_id)->withoutRelations();
        $this->terms_accepted =true;
    }

    #[Computed()]
    public function component(): string
    {
        if(!$this->terms_accepted)
            return Terms::class;

        if(!$this->signature_accepted)
            return Signature::class;
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-".md5(time());
    }

    public function mount()
    {
        
        MultiDB::setDb($this->db);

        // @phpstan-ignore-next-line
        $this->invite = \App\Models\InvoiceInvitation::with('invoice','contact.client','company')->withTrashed()->find($this->invitation_id);
        $invoice = $this->invite->invoice;
        $company = $this->invite->company;
        $contact = $this->invite->contact;
        $client = $this->invite->contact->client;
        $this->variables = ($this->invite && auth()->guard('contact')->user()->client->getSetting('show_accept_invoice_terms')) ? (new HtmlEngine($this->invite))->generateLabelsAndValues() : false;

        $this->settings = $client->getMergedSettings();

    }

    public function render()
    {
         return render('components.livewire.invoice-pay', [
            'context' => [
                'settings' => $this->settings,
                'invoice' => $this->invite->invoice,
                'variables' => $this->variables,
            ],
        ]);
    }
}
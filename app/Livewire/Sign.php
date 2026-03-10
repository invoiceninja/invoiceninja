<?php

namespace App\Livewire;

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\On;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Livewire\Flow2\DocuNinja;
use App\Models\InvoiceInvitation;
use Livewire\Attributes\Computed;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\WithSecureContext;

class Sign extends Component
{
    use WithSecureContext;

    public $invitation_id;
    public $entity_type;
    public $db;

    public $docu_ninja_ready = false;
    public $signature_accepted = false;

    public $request_hash;
    
    public $initializing = true;

    public $_key;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->bulkSetContext($this->_key, [
            'entity_type' => $this->entity_type,
            'db' => $this->db,
            'invitation_id' => $this->invitation_id,
            'request_hash' => $this->request_hash,
        ]);

        $this->initializing = false;
    }

    /**
     * Resolve the invitation model based on entity_type
     * 
     * @param int $invitation_id
     * @return InvoiceInvitation|QuoteInvitation|CreditInvitation|PurchaseOrderInvitation|null
     */
    protected function resolveInvitationModel(int $invitation_id)
    {
        return match($this->entity_type) {
            'invoice' => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
            'quote' => QuoteInvitation::withTrashed()->with('contact.client', 'quote')->find($invitation_id),
            'credit' => CreditInvitation::withTrashed()->with('contact.client', 'credit')->find($invitation_id),
            'purchase_order' => PurchaseOrderInvitation::withTrashed()->with('contact.vendor', 'purchase_order')->find($invitation_id),
            default => InvoiceInvitation::withTrashed()->with('contact.client', 'invoice')->find($invitation_id),
        };
    }

    #[Computed()]
    public function component(): ?string
    {
        
        if ($this->docu_ninja_ready) {
            return DocuNinja::class;
        } 
        else{
            return DocuNinjaLoader::class;
        }
        
    }

    #[On('docuninja-signature-captured')]
    public function docuNinjaSignatureCaptured()
    {
           
        if (!$this->docu_ninja_ready) {
            return;
        }

        if (!$this->signature_accepted) {
            $this->signature_accepted = true;
        }

        $this->processPayment();

    }

    #[On('docuninja-loader-ready')]
    public function docuninjaLoaderReady()
    {
        $this->docu_ninja_ready = true;    
    }

    public function processPayment()
    {

        $invitation = $this->resolveInvitationModel($this->invitation_id);
        $invitation->{$this->entity_type}->sync->dn_completed = true;
        $invitation->{$this->entity_type}->save();

        if($this->entity_type == 'invoice')
            $this->redirectRoute('client.payments.process', ['request_hash' => $this->request_hash]);
        elseif($this->entity_type == 'quote' && $this->request_hash)
            $this->redirectRoute('client.quotes.bulk', ['request_hash' => $this->request_hash]);
        elseif($this->entity_type == 'quote')
            $this->dispatch('quote-signed');

    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "sign-" . md5(microtime());
    }

    public function render()
    {
        return render('components.livewire.sign', ['_key' => $this->_key]);
    }
}
<?php

namespace App\Livewire\Flow2;

use Livewire\Component;
use App\Libraries\MultiDB;
use App\DataMapper\InvoiceSync;
use App\DataMapper\PurchaseOrderSync;
use App\DataMapper\QuoteSync;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\WithSecureContext;

class DocuNinjaLoader extends Component
{
    use WithSecureContext;

    public $invitation_id;
    public $isLoading = true;
    public $error = null;
    public $isReady = false;

    public $entity_type;

    public $_key;

    private function getInvitation()
    {
        return match($this->getContext($this->_key)['entity_type']){
            'invoice' => InvoiceInvitation::withTrashed()->find($this->invitation_id),
            'quote' => QuoteInvitation::withTrashed()->find($this->invitation_id),
            'credit' => CreditInvitation::withTrashed()->find($this->invitation_id),
            'purchase_order' => PurchaseOrderInvitation::withTrashed()->find($this->invitation_id),
            default => InvoiceInvitation::withTrashed()->find($this->invitation_id),
        };
    }

    public function mount()
    {
        // Set database context
        MultiDB::setDb($this->getContext($this->_key)['db']);
        $this->entity_type = $this->getContext($this->_key)['entity_type'];

     }

    public function loadDocuNinjaData()
    {
        try {
            
            $invitation = $this->getInvitation();
            
            if (!$invitation) {
                throw new \Exception('Invoice invitation not found');
            }


            // Check if DocuNinja is already completed
            if(isset($invitation->{$this->entity_type}->sync->dn_completed) && $invitation->{$this->entity_type}->sync->dn_completed === true){
                $this->dispatch('docuninja-signature-captured'); 
                $this->isLoading = false;
                return;
            }
            elseif(!$invitation->can_sign && $invitation->{$this->entity_type}->invitations()->where('can_sign', true)->count() >= 1)
            {
                // A special edge case exists for old invitations where the can_sign flag is not set.
                // For this scenario - the first user to view the doc, will have can_sign set to true.
                $this->error = 'You are not authorized to sign this document.';
                $this->isLoading = false;
                return;
            }
            elseif($invitation->can_sign &&
                isset($invitation->{$this->entity_type}->sync) && 
                $dn_invite = $invitation->{$this->entity_type}->sync->getInvitation($invitation->key)){
                
                $signable = [
                    'document_id' => $dn_invite['dn_id'],
                    'document_invitation_id' => $dn_invite['dn_invitation_id'],
                    'sig' => $dn_invite['dn_sig'],
                    'success' => !$invitation->{$this->entity_type}->sync->dn_completed,
                ];
                
            }
            // Generate new DocuNinja signable data
            else{
                
                //Handle edge case where the can_sign flag is not set for any invites
                if(!$invitation->can_sign){
                    $invitation->can_sign = true;
                    $invitation->saveQuietly();
                }

                $signable = $invitation->{$this->entity_type}->service()->getDocuNinjaSignable($invitation);
                
                $sync = match($this->entity_type) {
                    'quote' => new QuoteSync(qb_id: '', dn_completed: false),
                    'purchase_order' => new PurchaseOrderSync(qb_id: '', dn_completed: false),
                    default => new InvoiceSync(qb_id: '', dn_completed: false),
                };
                $sync->addInvitation(
                    $signable['invitation_key'],
                    $signable['document_id'],
                    $signable['document_invitation_id'],
                    $signable['sig']
                );
                $invitation->{$this->entity_type}->sync = $sync;
                $invitation->{$this->entity_type}->save();
                 
            }

            // Check if signing is not successful (already completed or error)
            if(isset($signable['success']) && !$signable['success']){
                $this->dispatch('docuninja-signature-captured');
                return;
            }

            // Store signable data in context for DocuNinja to read
            $this->setContext($this->_key, 'docuninja_signable', [
                'document_id' => $signable['document_id'],
                'document_invitation_id' => $signable['document_invitation_id'],
                'sig' => $signable['sig'],
                'company_key' => $invitation->company->company_key,
            ]);

            // Mark as ready and dispatch event to parent
            $this->isReady = true;
            $this->isLoading = false;

            // Dispatch event to InvoicePay/Sign to switch to DocuNinja component
            $this->dispatch('docuninja-loader-ready');

        } catch (\Exception $e) {
                      
            $this->error = 'Failed to load DocuNinja data: ' . $e->getMessage();
            $this->isLoading = false;
        }
    }

    // Method to retry loading if there was an error
    public function retryLoading()
    {
        $this->error = null;
        $this->isLoading = true;
        $this->isReady = false;
        
        $this->loadDocuNinjaData();
    }

    public function render()
    {
        return view('portal.ninja2020.flow2.docu-ninja-loader');
    }
}

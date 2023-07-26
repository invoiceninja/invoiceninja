<?php

namespace App\Jobs\Invoice;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class InjectSignature implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder
     */
    public $entity;

    /**
     * @var string
     */
    public $signature;

    public $contact_id;

    /**
     * Create a new job instance.
     *
     * @param $entity
     * @param string $signature
     */
    public function __construct($entity, $contact_id, string $signature)
    {
        $this->entity = $entity;

        $this->contact_id = $contact_id;

        $this->signature = $signature;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invitation = false;

        if($this->entity instanceof PurchaseOrder){
            $invitation = $this->entity->invitations()->where('vendor_contact_id', $this->contact_id)->first();

            if(!$invitation)
                $invitation = $this->entity->invitations->first();

        }
        else {
            
            $invitation = $this->entity->invitations()->where('client_contact_id', $this->contact_id)->first();

            if(!$invitation)
                $invitation = $this->entity->invitations->first();
        }
        
        if (! $invitation) {
            return;
        }

        $invitation->signature_base64 = $this->signature;
        $invitation->signature_date = now();
        $invitation->save();

    
    }
}

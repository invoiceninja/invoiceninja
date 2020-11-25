<?php

namespace App\Jobs\Invoice;

use App\Jobs\Entity\CreateEntityPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InjectSignature implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var App\Models\Invoice|App\Models\Quote
     */
    public $entity;

    /**
     * @var string
     */
    public $signature;

    /**
     * Create a new job instance.
     *
     * @param $entity
     * @param string $signature
     */
    public function __construct($entity, string $signature)
    {
        $this->entity = $entity;

        $this->signature = $signature;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invitation = $this->entity->invitations->whereNotNull('signature_base64')->first();

        if (! $invitation) {
            return;
        }

        $invitation->signature_base64 = $this->signature;
        $invitation->save();

        CreateEntityPdf::dispatch($invitation);
    }
}

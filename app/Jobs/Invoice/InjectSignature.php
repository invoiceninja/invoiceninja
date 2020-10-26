<?php

namespace App\Jobs\Invoice;

use App\Jobs\Entity\CreateEntityPdf;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InjectSignature implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var App\Models\Invoice
     */
    public $invoice;

    /**
     * @var string
     */
    public $signature;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, string $signature)
    {
        $this->invoice = $invoice;

        $this->signature = $signature;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invitation = $this->invoice->invitations->whereNotNull('signature_base64')->first();

        if (! $invitation) {
            return;
        }

        $invitation->signature_base64 = $this->signature;
        $invitation->save();

        CreateEntityPdf::dispatch($invitation);
    }
}

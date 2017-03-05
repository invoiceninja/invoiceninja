<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendInvoiceEmail.
 */
class SendPushNotification extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var string
     */
    protected $type;

    /**
     * Create a new job instance.

     * @param Invoice $invoice
     * @param mixed   $type
     */
    public function __construct($invoice, $type)
    {
        $this->invoice = $invoice;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @param PushService $pushService
     */
    public function handle(PushService $pushService)
    {
        $pushService->sendNotification($this->invoice, $this->type);
    }
}

<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\PushService;
use Monolog\Logger;
use Carbon;

/**
 * Class SendInvoiceEmail
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

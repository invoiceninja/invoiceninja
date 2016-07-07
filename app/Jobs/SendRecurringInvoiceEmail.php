<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer;
use Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Monolog\Logger;

class SendRecurringInvoiceEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     *
     * @return bool
     */
    public function handle(ContactMailer $mailer)
    {
        $mailer->sendInvoice($this->invoice);
        $this->invoice->last_sent_date = Carbon::now()->toDateString();
        $this->invoice->update();
    }

    /**
     * Handle a job failure.
     *
     * @param ContactMailer $mailer
     * @param Logger $logger
     */
    public function failed(ContactMailer $mailer, Logger $logger)
    {
        $this->jobName = $this->job->getName();
        parent::failed($mailer, $logger);
    }
}

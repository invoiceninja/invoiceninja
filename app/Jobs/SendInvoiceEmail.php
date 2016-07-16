<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer;
use Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Monolog\Logger;

/**
 * Class SendInvoiceEmail
 */
class SendInvoiceEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var bool
     */
    protected $reminder;

    /**
     * @var string
     */
    protected $pdf;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     * @param string $pdf
     * @param bool $reminder
     */
    public function __construct(Invoice $invoice, $pdf = '', $reminder = false)
    {
        $this->invoice = $invoice;
        $this->reminder = $reminder;
        $this->pdf = $pdf;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(ContactMailer $mailer)
    {
        $mailer->sendInvoice(
            $this->invoice, $this->reminder, $this->pdf
        );
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

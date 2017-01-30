<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Logger;

/**
 * Class SendInvoiceEmail.
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
    protected $pdfString;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     * @param string  $pdf
     * @param bool    $reminder
     * @param mixed   $pdfString
     */
    public function __construct(Invoice $invoice, $reminder = false, $pdfString = false)
    {
        $this->invoice = $invoice;
        $this->reminder = $reminder;
        $this->pdfString = $pdfString;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(ContactMailer $mailer)
    {
        $mailer->sendInvoice($this->invoice, $this->reminder, $this->pdfString);
    }

    /*
     * Handle a job failure.
     *
     * @param ContactMailer $mailer
     * @param Logger $logger
     */
     /*
    public function failed(ContactMailer $mailer, Logger $logger)
    {
        $this->jobName = $this->job->getName();

        parent::failed($mailer, $logger);
    }
    */
}

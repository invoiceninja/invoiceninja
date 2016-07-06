<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

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
    }

    /**
     * Handle a job failure.
     *
     * @param ContactMailer $mailer
     */
    public function failed(ContactMailer $mailer)
    {
        $mailer->sendTo(
            config('queue.failed.notify_email'),
            config('mail.from.address'),
            config('mail.from.name'),
            config('queue.failed.notify_subject'),
            'job_failed'
        );
    }
}

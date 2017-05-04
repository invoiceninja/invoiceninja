<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Monolog\Logger;
use Auth;
use App;

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
     * @var array
     */
    protected $template;

    /**
     * @var int
     */
    protected $userId;

    /**
     * @var string
     */
    protected $server;

    /**
     * Create a new job instance.
     *
     * @param Invoice $invoice
     * @param string  $pdf
     * @param bool    $reminder
     * @param mixed   $pdfString
     */
    public function __construct(Invoice $invoice, $userId = false, $reminder = false, $template = false)
    {
        $this->invoice = $invoice;
        $this->userId = $userId;
        $this->reminder = $reminder;
        $this->template = $template;
        $this->server = config('database.default');
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(ContactMailer $mailer)
    {
        // send email as user
        if (App::runningInConsole() && $this->userId) {
            Auth::onceUsingId($this->userId);
        }

        $mailer->sendInvoice($this->invoice, $this->reminder, $this->template);

        if (App::runningInConsole() && $this->userId) {
            Auth::logout();
        }
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

<?php

namespace App\Jobs\Payment;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Company;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;

    public $email_builder;

    private $contact;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Payment $payment, $email_builder, $contact)
    {
        $this->payment = $payment;
        $this->email_builder = $email_builder;
        $this->contact = $contact;
    }


    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        if ($this->contact->email) {
            Mail::to($this->contact->email, $this->contact->present()->name())
                ->send(new TemplateEmail($this->email_builder, $this->contact->user, $this->contact->customer));

            if (count(Mail::failures()) > 0) {
                event(new PaymentWasEmailedAndFailed($this->payment, Mail::failures(), Ninja::eventVars()));

                return $this->logMailError(Mail::failures());
            }

            //fire any events
            event(new PaymentWasEmailed($this->payment, $this->payment->company, Ninja::eventVars()));

            //sleep(5);
        }
    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->payment->client
        );
    }
}

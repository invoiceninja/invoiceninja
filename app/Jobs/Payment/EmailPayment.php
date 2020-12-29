<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Payment;

use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Jobs\Mail\BaseMailerJob;
use App\Libraries\MultiDB;
use App\Mail\Engine\PaymentEmailEngine;
use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Payment;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailPayment extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;

    public $email_builder;

    private $contact;

    private $company;

    public $settings;
    
    /**
     * Create a new job instance.
     *
     * @param Payment $payment
     * @param $email_builder
     * @param $contact
     * @param $company
     */
    public function __construct(Payment $payment, Company $company, ClientContact $contact)
    {
        $this->payment = $payment;
        $this->contact = $contact;
        $this->company = $company;
        $this->settings = $payment->client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        if ($this->company->is_disabled) {
            return true;
        }
        
        if ($this->contact->email) {
            MultiDB::setDb($this->company->db);

            //if we need to set an email driver do it now
            $this->setMailDriver();

            $email_builder = (new PaymentEmailEngine($this->payment, $this->contact))->build();

            try {
                $mail = Mail::to($this->contact->email, $this->contact->present()->name());
                $mail->send(new TemplateEmail($email_builder, $this->contact->user, $this->contact->client));
            } catch (\Exception $e) {
                nlog("mailing failed with message " . $e->getMessage());
                event(new PaymentWasEmailedAndFailed($this->payment, $this->company, Mail::failures(), Ninja::eventVars()));
                $this->failed($e);
                return $this->logMailError($e->getMessage(), $this->payment->client);
            }

            event(new PaymentWasEmailed($this->payment, $this->payment->company, Ninja::eventVars()));
        }
    }
}

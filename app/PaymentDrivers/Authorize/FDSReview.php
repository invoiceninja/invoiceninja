<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Authorize;

use App\Libraries\MultiDB;
use App\Models\PaymentHash;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use Symfony\Component\Mime\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/*Multi Mailer implemented*/

class FDSReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    
    public $tries = 1; //number of retries
    public $deleteWhenMissingModels = true;

    public function __construct(private string $transaction_reference, private PaymentHash $payment_hash, private string $db)
    {
    }

    public function handle()
    {

        MultiDB::setDB($this->db);
        
        $company = $this->payment_hash->fee_invoice->company;

        App::setLocale($company->getLocale());
        
        $invoices_string = \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()) ?: '';

        $body = "Transaction {$this->transaction_reference} has been held for your review in Auth.net based on your Fraud Detection Settings.\n We have marked invoices {$invoices_string} as paid in Invoice Ninja.\n Please review these transaction in your auth.net account, and authorize them if they are correct to ensure the transactions are processed as expected. If these charges need to be cancelled, you will need to delete the payments that have been created in Invoice Ninja to reverse those payments.";

        $mo = new EmailObject();
        $mo->subject = "Transaction {$this->transaction_reference} held for review by auth.net";
        $mo->body = $body;
        $mo->text_body = $body;
        $mo->company_key = $company->company_key;
        $mo->html_template = 'email.template.generic';
        $mo->to = [new Address($company->owner()->email, $company->owner()->present()->name())];
        // $mo->email_template_body = 'nordigen_requisition_body';
        // $mo->email_template_subject = 'nordigen_requisition_subject';

        Email::dispatch($mo, $company);


    }

}

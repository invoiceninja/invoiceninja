<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Invoice;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice)
    {

        $this->invoice = $invoice;

    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {
        /*Jobs are not multi-db aware, need to set! */
        MultiDB::setDB($this->invoice->company->db);

        $message_array = $this->invoice->getEmailData();
        $message_array['title'] = &$message_array['subject'];
        $message_array['footer'] = 'The Footer';
        //

        $variables = array_merge($this->invoice->makeLabels(), $this->invoice->makeValues());

        $template_style = $this->invoice->client->getSetting('email_style');
        
        $this->invoice->invitations->each(function ($invitation) use($message_array, $template_style, $variables){

            if($invitation->contact->send_invoice && $invitation->contact->email)
            {
                //there may be template variables left over for the specific contact? need to reparse here //todo this wont work, as if the variables existed, they'll be overwritten already!
                $message_array['body'] = str_replace(array_keys($variables), array_values($variables), $message_array['body']);
                $message_array['subject'] = str_replace(array_keys($variables), array_values($variables), $message_array['subject']);

                //change the runtime config of the mail provider here:
                
                //send message
                Mail::to($invitation->contact->email)
                ->send(new TemplateEmail($message_array, $template_style, $invitation->contact->user, $invitation->contact->client));

                if( count(Mail::failures()) > 0 ) {

                    event(new InvoiceWasEmailedAndFailed($this->invoice, Mail::failures()));
                    
                    return $this->logMailError($errors);

                }

                //fire any events
                event(new InvoiceWasEmailed($this->invoice));

                sleep(5);
                
            }

        });

    }

    private function logMailError($errors)
    {

        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->invoice->client
        );

    }

}
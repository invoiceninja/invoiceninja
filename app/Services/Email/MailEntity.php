<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\Utils\Ninja;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Services\Email\MailBuild;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use Illuminate\Queue\InteractsWithQueue;
use App\DataMapper\Analytics\EmailSuccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MailEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;

    public MailObject $mail_object;

    public Mailable $mailable;

    public Mail $mail;
    
    public ?string $client_postmark_secret = null;
    
    public ?string $client_mailgun_secret = null;
    
    public ?string $client_mailgun_domain = null;
    
    public bool $override = false;
        
    private string $mailer = '';
    
    public $invitation;

    private ?string $db;

    public int $tries = 4;
    
    public $deleteWhenMissingModels = true;

    public function __construct($invitation, $db, $mail_object)
    {

        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->db = $db;

        $this->mail_object = $mail_object;

        $this->override = $mail_object->override;

    }

    public function handle(): void
    {
        
        MultiDB::setDb($this->db);
        
        $this->companyCheck();
        
        $builder = new MailBuild($this);

        //construct mailable
        $builder->run($this);

        $this->mailable = $builder->getMailable();

        //spam checks
        if($this->preFlightChecksFail())
            return;

        $this->setMailDriver()
             ->trySending();

        
        //what do we pass into a generaic builder?
        
        //construct mailer

    }

    public function companyCheck(): void
    {
        /* Handle bad state */
        if(!$this->company)
            $this->fail();

        /* Handle deactivated company */
        if($this->company->is_disabled && !$this->override) 
            $this->fail();

        /* To handle spam users we drop all emails from flagged accounts */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->is_flagged) 
            $this->fail();

    }

    public function configureMailer(): self
    {
        $this->setMailDriver();

        $this->mail = Mail::mailer($this->mailer);
        
        return $this;
    }


    /** 
     * Sets the mail driver to use and applies any specific configuration 
     * the the mailable
     */
	private function setMailDriver(): self
    {

        switch ($this->mail_object->settings->email_sending_method) {
            case 'default':
                $this->mailer = config('mail.default');
                break;
            // case 'gmail':
            //     $this->mailer = 'gmail';
            //     $this->setGmailMailer();
            //     return $this;
            // case 'office365':
            //     $this->mailer = 'office365';
            //     $this->setOfficeMailer();
            //     return $this;
            // case 'client_postmark':
            //     $this->mailer = 'postmark';
            //     $this->setPostmarkMailer();
            //     return $this;
            // case 'client_mailgun':
            //     $this->mailer = 'mailgun';
            //     $this->setMailgunMailer();
            //     return $this;

            default:
                break;
        }

        if(Ninja::isSelfHost())
            $this->setSelfHostMultiMailer();

        return $this;

    }

    /**
     * Allows configuration of multiple mailers
     * per company for use by self hosted users
     */
    private function setSelfHostMultiMailer(): void
    {

        if (env($this->company->id . '_MAIL_HOST')) 
        {

            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => env($this->company->id . '_MAIL_HOST'),
                    'port' => env($this->company->id . '_MAIL_PORT'),
                    'username' => env($this->company->id . '_MAIL_USERNAME'),
                    'password' => env($this->company->id . '_MAIL_PASSWORD'),
                ],
            ]);

            if(env($this->company->id . '_MAIL_FROM_ADDRESS'))
            {
            $this->mailable
                 ->from(env($this->company->id . '_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')), env($this->company->id . '_MAIL_FROM_NAME', env('MAIL_FROM_NAME')));
            }

        }

    }


    /**
     * Ensure we discard any data that is not required
     * 
     * @return void
     */
    private function cleanUpMailers(): void
    {
        $this->client_postmark_secret = false;

        $this->client_mailgun_secret = false;

        $this->client_mailgun_domain = false;

        //always dump the drivers to prevent reuse 
        app('mail.manager')->forgetMailers();
    }


    public function trySending()
    {
           try {

            $mail = Mail::mailer($this->mailer);
            $mail->send($this->mailable);

            /* Count the amount of emails sent across all the users accounts */
            Cache::increment($this->company->account->key);

            LightLogs::create(new EmailSuccess($this->company->company_key))
                     ->send();

         }
        catch(\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
                nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
                $this->fail();
                $this->cleanUpMailers();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());
                return;
        }
        catch(\Symfony\Component\Mime\Exception\LogicException $e){
                nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
                $this->fail();
                $this->cleanUpMailers();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());
                return;
        }
        catch (\Exception | \Google\Service\Exception $e) {
            
            nlog("Mailer failed with {$e->getMessage()}");
            $message = $e->getMessage();

            /**
             * Post mark buries the proper message in a a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if(stripos($e->getMessage(), 'code 406') || stripos($e->getMessage(), 'code 300') || stripos($e->getMessage(), 'code 413')) 
            { 

                $message = "Either Attachment too large, or recipient has been suppressed.";

                $this->fail();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());
                $this->cleanUpMailers();

                return;

            }

            //only report once, not on all tries
            if($this->attempts() == $this->tries)
            {

                /* If the is an entity attached to the message send a failure mailer */
                if($this->mail_object->entity_id)
                    $this->entityEmailFailed($message);

                /* Don't send postmark failures to Sentry */
                if(Ninja::isHosted() && (!$e instanceof ClientException)) 
                    app('sentry')->captureException($e);

            }
        
            /* Releasing immediately does not add in the backoff */
            $this->release($this->backoff()[$this->attempts()-1]);

        }
    }

   /**
     * On the hosted platform we scan all outbound email for 
     * spam. This sequence processes the filters we use on all
     * emails.
     */
    public function preFlightChecksFail(): bool
    {

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if($this->hasInValidEmails())
            return true;

        /* GMail users are uncapped */
        if(in_array($this->mail_object->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun'])) 
            return false;

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if($this->company->account && $this->company->account->emailQuotaExceeded())
            return true;

        /* If the account is verified, we allow emails to flow */
        if($this->company->account && $this->company->account->is_verified_account) {

            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            // return false;
        }


        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if($this->company->account && !$this->company->account->account_sms_verified){
            
            if(class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class))
                (new \Modules\Admin\Jobs\Account\EmailFilter($this->mail_object, $this->company))->run();

            return true;

        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if(class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class))
            (new \Modules\Admin\Jobs\Account\EmailFilter($this->mail_object, $this->company))->run();

        return false;

    }

    
    /**
     * Checks if emails are have some illegal / required characters.
     *
     * @return bool
     */
    private function hasInValidEmails(): bool
    {

        foreach($this->mail_object->to as $address_object)
        {

            if(strpos($address_object->address, '@example.com') !== false)
                return true;

            if(!str_contains($address_object->address, "@"))
                return true;

            if($address_object->address == " ")
                return true;
        }


        return false;
    }
    
    /**
     * Backoff time
     *
     * @return void
     */
    public function backoff()
    {
        return [5, 10, 30, 240];
    }
    
    /**
     * Failed handler
     *
     * @param  mixed $exception
     * @return void
     */
    public function failed($exception = null)
    {
    nlog("dying now");

        config(['queue.failed.driver' => null]);

    }
}

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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Services\Email\MailBuild;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BaseMailer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Company $company;

    public int $tries = 4;

    public ?string $client_postmark_secret = false;

    public ?string $client_mailgun_secret = null;

    public ?string $client_mailgun_domain = null;

    public boolean $override = false;

    public $deleteWhenMissingModels = true;

    public function __construct()
    {
    }

    public function handle(MailBuild $builder): void
    {
    }

    public function companyCheck()
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

        return $this;
    }

    public function trySending()
    {
           try {

            $mailer
                ->to($this->nmo->to_user->email)
                ->send($this->nmo->mailable);

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
                if($this->nmo->entity)
                    $this->entityEmailFailed($message);

                /* Don't send postmark failures to Sentry */
                if(Ninja::isHosted() && (!$e instanceof ClientException)) 
                    app('sentry')->captureException($e);

            }
        
            /* Releasing immediately does not add in the backoff */
            $this->release($this->backoff()[$this->attempts()-1]);

        }
    }

    public function backoff()
    {
        return [5, 10, 30, 240];
    }

    public function failed($exception = null)
    {

        config(['queue.failed.driver' => null]);

    }
}
`
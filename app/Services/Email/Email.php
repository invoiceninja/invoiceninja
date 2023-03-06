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

use App\Models\User;
use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\VendorContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Libraries\Google\Google;
use App\Models\InvoiceInvitation;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use Illuminate\Queue\InteractsWithQueue;
use GuzzleHttp\Exception\ClientException;
use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;

class Email implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 4; //number of retries

    public $deleteWhenMissingModels = true;

    public $override;

    private $mailer;

    protected $client_postmark_secret = false;

    protected $client_mailgun_secret = false;

    protected $client_mailgun_domain = false;

    public $mailable;

    public function __construct(public EmailObject $email_object, public Company $company)
    {
    }

    public function backoff()
    {
        return [10, 30, 60, 240];
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->setOverride()
             ->initModels()
             ->setDefaults()
             ->buildMailable()
             ->preFlightChecks()
             ->email();
    }

    public function setOverride(): self
    {
        $this->override = $this->email_object->override;

        return $this;
    }

    public function initModels(): self
    {

        $this->email_object->entity_id ? $this->email_object->entity = $this->email_object->entity_class::withTrashed()->with('invitations')->find($this->email_object->entity_id) : $this->email_object->entity = null;

        $this->email_object->invitation_id ? $this->email_object->invitation = $this->email_object->entity->invitations()->where('id', $this->email_object->invitation_id)->first() : $this->email_object->invitation = null;

        $this->email_object->client_id ? $this->email_object->client = Client::withTrashed()->find($this->email_object->client_id) : $this->email_object->client = null;

        $this->email_object->vendor_id ? $this->email_object->vendor = Vendor::withTrashed()->find($this->email_object->vendor_id) : $this->email_object->vendor = null;
        
        $this->email_object->user_id ? $this->email_object->user = User::withTrashed()->find($this->email_object->user_id) :  $this->email_object->user = $this->company->owner();

        $this->email_object->company_key = $this->company->company_key;

        $this->email_object->vendor_contact_id ? $this->email_object->contact = VendorContact::withTrashed()->find($this->email_object->vendor_contact_id) :  null;

        $this->email_object->client_contact_id ? $this->email_object->contact = ClientContact::withTrashed()->find($this->email_object->client_contact_id) :  null;
        
        $this->email_object->client_id ? $this->email_object->settings  = $this->email_object->client->getMergedSettings() : $this->email_object->settings = $this->company->settings;

        $this->email_object->whitelabel = $this->company->account->isPaid() ? true : false;

        $this->email_object->logo = $this->email_object->settings->company_logo;

        $this->email_object->signature = $this->email_object->settings->email_signature;

        return $this;
    }

    public function setDefaults(): self
    {
        (new EmailDefaults($this))->run();

        return $this;
    }

    public function buildMailable(): self
    {
        
        $this->mailable = new EmailMailable($this->email_object);
        
        return $this;
        
    }

    public function preFlightChecks(): self
    {

        return $this;
    }

    public function email()
    {
    }

}
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

namespace App\Services\InboundMail;

use App\Events\Expense\ExpenseWasCreated;
use App\Factory\ExpenseFactory;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company; 
use App\Models\SystemLog;
use App\Models\VendorContact;
use App\Services\InboundMail\InboundMail;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\MakesHash;
use Cache;
use Illuminate\Queue\SerializesModels;

class InboundMailEngine
{
    use SerializesModels, MakesHash;
    use GeneratesCounter, SavesDocuments;

    private ?bool $isUnknownRecipent = null;
    private array $globalBlacklistDomains = [];
    private array $globalBlacklistSenders = [];
    private array $globalWhitelistDomains = []; // only for global validation, not for allowing to send something into the company, should be used to disabled blocking for mass-senders
    private array $globalWhitelistSenders = []; // only for global validation, not for allowing to send something into the company, should be used to disabled blocking for mass-senders
    public function __construct()
    {
    }
    /**
     * if there is not a company with an matching mailbox, we only do monitoring
     * reuse this method to add more mail-parsing behaviors
     */
    public function handleExpenseMailbox(InboundMail $email)
    {
        if ($this->isInvalidOrBlocked($email->from, $email->to))
            return;

        // Expense Mailbox => will create an expense
        $company = MultiDB::findAndSetDbByExpenseMailbox($email->to);
        if (!$company) {
            $this->saveMeta($email->from, $email->to, true);
            return;
        }

        $this->createExpense($company, $email);
        $this->saveMeta($email->from, $email->to);
    }

    // SPAM Protection
    public function isInvalidOrBlocked(string $from, string $to)
    {
        // invalid email
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            nlog('E-Mail blocked, because from e-mail has the wrong format: ' . $from);
            return true;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            nlog('E-Mail blocked, because to e-mail has the wrong format: ' . $from);
            return true;
        }

        $parts = explode('@', $from);
        $domain = array_pop($parts);

        // global blacklist
        if (in_array($from, $this->globalWhitelistDomains)) {
            return false;
        }
        if (in_array($domain, $this->globalBlacklistDomains)) {
            nlog('E-Mail blocked, because the domain was found on globalBlocklistDomains: ' . $from);
            return true;
        }
        if (in_array($domain, $this->globalWhitelistSenders)) {
            return false;
        }
        if (in_array($from, $this->globalBlacklistSenders)) {
            nlog('E-Mail blocked, because the email was found on globalBlocklistEmails: ' . $from);
            return true;
        }

        if (Cache::has('inboundMailBlockedSender:' . $from)) { // was marked as blocked before, so we block without any console output
            return true;
        }

        // sender occured in more than 500 emails in the last 12 hours
        $senderMailCountTotal = Cache::get('inboundMailCountSender:' . $from, 0);
        if ($senderMailCountTotal >= config('global_inbound_sender_permablock_mailcount')) {
            nlog('E-Mail blocked permanent, because the sender sended more than ' . $senderMailCountTotal . ' emails in the last 12 hours: ' . $from);
            $this->blockSender($from);
            $this->saveMeta($from, $to);
            return true;
        }
        if ($senderMailCountTotal >= config('global_inbound_sender_block_mailcount')) {
            nlog('E-Mail blocked, because the sender sended more than ' . $senderMailCountTotal . ' emails in the last 12 hours: ' . $from);
            $this->saveMeta($from, $to);
            return true;
        }

        // sender sended more than 50 emails to the wrong mailbox in the last 6 hours
        $senderMailCountUnknownRecipent = Cache::get('inboundMailCountSenderUnknownRecipent:' . $from, 0);
        if ($senderMailCountUnknownRecipent >= config('company_inbound_sender_block_unknown_reciepent')) {
            nlog('E-Mail blocked, because the sender sended more than ' . $senderMailCountUnknownRecipent . ' emails to the wrong mailbox in the last 6 hours: ' . $from);
            $this->saveMeta($from, $to);
            return true;
        }

        // wrong recipent occurs in more than 100 emails in the last 12 hours, so the processing is blocked
        $mailCountUnknownRecipent = Cache::get('inboundMailCountUnknownRecipent:' . $to, 0); // @turbo124 maybe use many to save resources in case of spam with multiple to addresses each time
        if ($mailCountUnknownRecipent >= 200) {
            nlog('E-Mail blocked, because anyone sended more than ' . $mailCountUnknownRecipent . ' emails to the wrong mailbox in the last 12 hours. Current sender was blocked as well: ' . $from);
            $this->blockSender($from);
            $this->saveMeta($from, $to);
            return true;
        }

        return false;
    }
    public function blockSender(string $from)
    {
        Cache::add('inboundMailBlockedSender:' . $from, true, now()->addHours(12));

        // TODO: ignore, when known sender (for heavy email-usage mostly on isHosted())
        // TODO: handle external blocking
    }
    public function saveMeta(string $from, string $to, bool $isUnknownRecipent = false)
    {
        // save cache
        Cache::add('inboundMailCountSender:' . $from, 0, now()->addHours(12));
        Cache::increment('inboundMailCountSender:' . $from);

        if ($isUnknownRecipent) {
            Cache::add('inboundMailCountSenderUnknownRecipent:' . $from, 0, now()->addHours(6));
            Cache::increment('inboundMailCountSenderUnknownRecipent:' . $from); // we save the sender, to may block him

            Cache::add('inboundMailCountUnknownRecipent:' . $to, 0, now()->addHours(12));
            Cache::increment('inboundMailCountUnknownRecipent:' . $to); // we save the sender, to may block him
        }
    }

    // MAIN-PROCESSORS
    protected function createExpense(Company $company, InboundMail $email)
    {
        // Skipping executions: will not result in not saving Metadata to prevent usage of these conditions, to spam
        if (!($company?->expense_mailbox_active ?: false)) {
            $this->logBlocked($company, 'mailbox not active for this company. from: ' . $email->from);
            return;
        }
        if (!$this->validateExpenseSender($company, $email)) {
            $this->logBlocked($company, 'invalid sender of an ingest email for this company. from: ' . $email->from);
            return;
        }
        if (sizeOf($email->documents) == 0) {
            $this->logBlocked($company, 'email does not contain any attachments and is likly not an expense. from: ' . $email->from);
            return;
        }

        // create expense
        $expense = ExpenseFactory::create($company->id, $company->owner()->id);

        $expense->public_notes = $email->subject;
        $expense->private_notes = $email->text_body;
        $expense->date = $email->date;

        // handle vendor assignment
        $expense_vendor = $this->getVendor($company, $email);
        if ($expense_vendor)
            $expense->vendor_id = $expense_vendor->id;

        // handle documents
        $this->processHtmlBodyToDocument($email);
        $documents = [];
        array_push($documents, ...$email->documents);
        if ($email->body_document !== null)
            array_push($documents, $email->body_document);

        $expense->saveQuietly();

        $this->saveDocuments($documents, $expense);

        event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null))); // @turbo124 please check, I copied from API-Controller
        event('eloquent.created: App\Models\Expense', $expense); // @turbo124 please check, I copied from API-Controller
    }

    // HELPERS
    private function processHtmlBodyToDocument(InboundMail $email)
    {

        if ($email->body !== null)
            $email->body_document = TempFile::UploadedFileFromRaw($email->body, "E-Mail.html", "text/html");

    }
    private function validateExpenseSender(Company $company, InboundMail $email)
    {
        $parts = explode('@', $email->from);
        $domain = array_pop($parts);

        // whitelists
        $email_whitelist = explode(",", $company->inbound_mailbox_whitelist_senders);
        if (in_array($email->from, $email_whitelist))
            return true;
        $domain_whitelist = explode(",", $company->inbound_mailbox_whitelist_domains);
        if (in_array($domain, $domain_whitelist))
            return true;
        $email_blacklist = explode(",", $company->inbound_mailbox_blacklist_senders);
        if (in_array($email->from, $email_blacklist))
            return false;
        $domain_blacklist = explode(",", $company->inbound_mailbox_blacklist_domains);
        if (in_array($domain, $domain_blacklist))
            return false;

        // allow unknown
        if ($company->inbound_mailbox_allow_unknown)
            return true;

        // own users
        if ($company->inbound_mailbox_allow_company_users && $company->users()->where("email", $email->from)->exists())
            return true;

        // from vendors
        if ($company->inbound_mailbox_allow_vendors && VendorContact::where("company_id", $company->id)->where("email", $email->from)->exists())
            return true;

        // from clients
        if ($company->inbound_mailbox_allow_clients && ClientContact::where("company_id", $company->id)->where("email", $email->from)->exists())
            return true;

        // denie
        return false;
    }
    private function getClient(Company $company, InboundMail $email)
    {
        $clientContact = ClientContact::where("company_id", $company->id)->where("email", $email->from)->first();
        if (!$clientContact)
            return null;

        return $clientContact->client();
    }
    private function getVendor(Company $company, InboundMail $email)
    {
        $vendorContact = VendorContact::where("company_id", $company->id)->where("email", $email->from)->first();
        if (!$vendorContact)
            return null;

        return $vendorContact->vendor();
    }
    private function logBlocked(Company $company, string $data)
    {
        nlog("[InboundMailEngine][company:" . $company->id . "] " . $data);

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_INBOUND_MAIL_BLOCKED,
                SystemLog::TYPE_CUSTOM,
                null,
                $company
            )
        )->handle();
    }
}

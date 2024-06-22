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

use App\Factory\ExpenseFactory;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\SystemLog;
use App\Models\VendorContact;
use App\Services\EDocument\Imports\ParseEDocument;
use App\Services\InboundMail\InboundMail;
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
    private array $globalBlacklist = explode(",", config('global_inbound_blocklist'));
    private array $globalWhitelist = explode(",", config('global_inbound_whitelist')); // only for global validation, not for allowing to send something into the company, should be used to disabled blocking for mass-senders
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

        $this->createExpenses($company, $email);
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
        if (in_array($from, $this->globalWhitelist)) {
            return false;
        }
        if (in_array($domain, $this->globalWhitelist)) {
            return false;
        }
        if (in_array($domain, $this->globalBlacklist)) {
            nlog('E-Mail blocked, because the domain was found on globalBlocklistDomains: ' . $from);
            return true;
        }
        if (in_array($from, $this->globalBlacklist)) {
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
    protected function createExpenses(Company $company, InboundMail $email)
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

        // prepare data
        $expense_vendor = $this->getVendor($company, $email);
        $this->processHtmlBodyToDocument($email);

        $parsed_expense_ids = []; // used to check if an expense was already matched within this job

        // check documents => optimal when parsed from any source => else create an expense for each document
        foreach ($email->documents as $document) {

            /** @var \App\Models\Expense $expense */
            $expense = null;

            // check if document can be parsed to an expense
            try {

                $expense = (new ParseEDocument($document))->run();

                // check if expense was already matched within this job and skip if true
                if (array_search($expense->id, $parsed_expense_ids))
                    continue;

                array_push($parsed_expenses, $expense->id);

            } catch (\Exception $err) {
                // throw error, only, when its not expected
                switch (true) {
                    case ($err->getMessage() === 'E-Invoice standard not supported'):
                    case ($err->getMessage() === 'File type not supported'):
                        break;
                    default:
                        throw $err;
                }
            }

            $is_imported_by_parser = array_search($expense->id, $parsed_expense_ids);

            // populate missing data with data from email
            if (!$expense)
                $expense = ExpenseFactory::create($company->id, $company->owner()->id);

            if ($is_imported_by_parser)
                $expense->public_notes = $expense->public_notes . $email->subject;

            if ($is_imported_by_parser)
                $expense->private_notes = $expense->private_notes . $email->text_body;

            if (!$expense->date)
                $expense->date = $email->date;

            if (!$expense->vendor_id && $expense_vendor)
                $expense->vendor_id = $expense_vendor->id;

            // save document only, when not imported by parser
            $documents = [];
            if ($is_imported_by_parser)
                array_push($documents, $document);

            // email document
            if ($email->body_document !== null)
                array_push($documents, $email->body_document);

            $this->saveDocuments($documents, $expense);

            if ($is_imported_by_parser)
                $expense->saveQuietly();
            else
                $expense->save();

        }
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
        $whitelist = explode(",", $company->inbound_mailbox_whitelist);
        if (in_array($email->from, $whitelist))
            return true;
        if (in_array($domain, $whitelist))
            return true;
        $blacklist = explode(",", $company->inbound_mailbox_blacklist);
        if (in_array($email->from, $blacklist))
            return false;
        if (in_array($domain, $blacklist))
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

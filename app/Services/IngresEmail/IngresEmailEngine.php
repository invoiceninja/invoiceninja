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

namespace App\Services\IngresEmail;

use App\Events\Expense\ExpenseWasCreated;
use App\Factory\ExpenseFactory;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\Email\EmailObject;
use App\Services\IngresEmail\IngresEmail;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IngresEmailEngine implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;
    use GeneratesCounter, SavesDocuments;

    private IngresEmail $email;
    private ?Company $company;
    private array $globalBlacklist = [];
    function __constructor(IngresEmail $email)
    {
        $this->email = $email;
    }
    /**
     * if there is not a company with an matching mailbox, we do nothing
     */
    public function handle()
    {
        // Expense Mailbox => will create an expense
        foreach ($this->email->to as $expense_mailbox) {
            $this->company = MultiDB::findAndSetDbByExpenseMailbox($expense_mailbox);
            if (!$this->company || !$this->validateExpenseActive())
                continue;

            $this->createExpense();
        }

        // TODO reuse this method to add more mail-parsing behaviors
    }

    // MAIN-PROCESSORS
    protected function createExpense()
    {
        if (!$this->validateExpenseSender()) {
            nlog('invalid sender of an ingest email to company: ' . $this->company->id . ' from: ' . $this->email->from);
            return;
        }

        $expense = ExpenseFactory::create($this->company->id, $this->company->owner()->id);

        $expense->public_notes = $this->email->subject;
        $expense->private_notes = $this->email->text_body;
        $expense->date = $this->email->date;

        // handle vendor assignment
        $expense_vendor = $this->getExpenseVendor();
        if ($expense_vendor)
            $expense->vendor_id = $expense_vendor->id;

        // handle documents
        $this->processHtmlBodyToDocument();
        $documents = [];
        array_push($documents, ...$this->email->documents);
        if ($this->email->body_document)
            $documents[] = $this->email->body_document;
        $this->saveDocuments($documents, $expense);

        $expense->saveQuietly();

        event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null))); // @turbo124 please check, I copied from API
        event('eloquent.created: App\Models\Expense', $expense); // @turbo124 please check, I copied from API

        return $expense;
    }

    // HELPERS
    private function processHtmlBodyToDocument()
    {
        if (!$this->email->body_document && property_exists($this->email, "body")) {
            $this->email->body_document = TempFile::UploadedFileFromRaw($this->email->body, "E-Mail.html", "text/html");
        }
    }
    private function validateExpenseActive()
    {
        return $this->company?->expense_mailbox_active ?: false;
    }
    private function validateExpenseSender()
    {
        // invalid email
        if (!filter_var($this->email->from, FILTER_VALIDATE_EMAIL))
            return false;

        $parts = explode('@', $this->email->from);
        $domain = array_pop($parts);

        // global blacklist
        if (in_array($domain, $this->globalBlacklist))
            return false;

        // whitelists
        $email_whitelist = explode(",", $this->company->expense_mailbox_whitelist_emails);
        if (in_array($this->email->from, $email_whitelist))
            return true;
        $domain_whitelist = explode(",", $this->company->expense_mailbox_whitelist_domains);
        if (in_array($domain, $domain_whitelist))
            return true;
        if ($this->company->expense_mailbox_allow_unknown && sizeOf($email_whitelist) == 0 && sizeOf($domain_whitelist) == 0) // from unknown only, when no whitelists are defined
            return true;

        // own users
        if ($this->company->expense_mailbox_allow_company_users && $this->company->users()->where("email", $this->email->from)->exists())
            return true;

        // from clients/vendors (if active)
        if ($this->company->expense_mailbox_allow_vendors && $this->company->vendors()->where("invoicing_email", $this->email->from)->orWhere($this->email->from, 'LIKE', "CONCAT('%',invoicing_domain)")->exists())
            return true;
        if ($this->company->expense_mailbox_allow_vendors && $this->company->vendors()->contacts()->where("email", $this->email->from)->exists()) // TODO
            return true;

        // denie
        return false;
    }
    private function getExpenseVendor()
    {
        $vendor = Vendor::where("company_id", $this->company->id)->where('invoicing_email', $this->email->from)->first();
        if ($vendor == null)
            $vendor = Vendor::where("company_id", $this->company->id)->where($this->email->from, 'LIKE', "CONCAT('%',invoicing_domain)")->first();
        if ($vendor == null) {
            $vendorContact = VendorContact::where("company_id", $this->company->id)->where("email", $this->email->from)->first();
            $vendor = $vendorContact->vendor();
        }
        // TODO: from contacts

        return $vendor;
    }
}

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

namespace App\Jobs\Mail;

use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use App\Events\Expense\ExpenseWasCreated;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Factory\ExpenseFactory;
use App\Helpers\Mail\ImapMailbox;
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\ExpenseRepository;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Turbo124\Beacon\Facades\LightLogs;

/*Multi Mailer implemented*/

class ExpenseMailboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 4; //number of retries

    public $deleteWhenMissingModels = true;
    private array $imap_companies;
    private array $imap_credentials;
    private $expense_repo;

    public function __construct()
    {
        $this->credentials = [];

        $this->getImapCredentials();

        $this->expense_repo = new ExpenseRepository(); // @turbo124 @todo is this the right aproach? should it be handled just with the model?
    }

    public function handle()
    {

        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            if (sizeOf($this->imap_credentials) != 0) {
                nlog("importing expenses from imap-servers");

                Company::whereIn('id', $this->imap_companies)->cursor()->each(function ($company) {
                    $this->handleImapCompany($company);
                });
            }
        }

    }

    private function getImapCredentials()
    {
        $servers = array_map('trim', explode(",", config('ninja.imap_inbound_expense.servers')));
        $ports = explode(",", config('ninja.imap_inbound_expense.servers'));
        $users = explode(",", config('ninja.imap_inbound_expense.servers'));
        $passwords = explode(",", config('ninja.imap_inbound_expense.servers'));
        $companies = explode(",", config('ninja.imap_inbound_expense.servers'));

        if (sizeOf($servers) != sizeOf($ports) || sizeOf($servers) != sizeOf($users) || sizeOf($servers) != sizeOf($passwords) || sizeOf($servers) != sizeOf($companies))
            throw new \Exception('invalid configuration imap_inbound_expenses (wrong element-count)');

        foreach ($companies as $index => $companyId) {
            $this->imap_credentials[$companyId] = [
                "server" => $servers[$index],
                "port" => $ports[$index] != '' ? $ports[$index] : null,
                "user" => $users[$index],
                "password" => $passwords[$index],
            ];
            $this->imap_companies[] = $companyId;
        }
    }

    private function handleImapCompany(Company $company)
    {
        nlog("importing expenses for company: " . $company->id);

        $credentials = $this->imap_credentials[$company->id];
        $imapMailbox = new ImapMailbox($credentials->server, $credentials->port, $credentials->user, $credentials->password);

        $emails = $imapMailbox->getUnprocessedEmails();

        foreach ($emails as $mail) {

            try {

                $sender = $mail->getSender();

                $vendor = Vendor::where('expense_sender_email', $sender)->first();
                if ($vendor == null)
                    $vendor = Vendor::where($sender, 'LIKE', "CONCAT('%',expense_sender_domain)")->first();
                if ($vendor == null)
                    $vendor = Vendor::where("email", $sender)->first();

                $documents = []; // TODO: $mail->getAttachments() + save email as document (.html)

                $data = [
                    "vendor_id" => $vendor !== null ? $vendor->id : null,
                    "date" => $mail->getDate(),
                    "public_notes" => $mail->getSubject(),
                    "private_notes" => $mail->getCompleteBodyText(),
                    "documents" => $documents, // FIXME: https://github.com/ddeboer/imap?tab=readme-ov-file#message-attachments
                ];

                $expense = $this->expense_repo->save($data, ExpenseFactory::create($company->company->id, $company->company->owner()->id)); // TODO: dont assign a new number at beginning

                event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null)));

                event('eloquent.created: App\Models\Expense', $expense);

                $mail->markAsSeen();
                $imapMailbox->moveProcessed($mail);

            } catch (\Exception $e) {
                $imapMailbox->moveFailed($mail);

                nlog("processing of an email failed upnormally: " . $company->id . " message: " . $e->getMessage()); // @turbo124 @todo should this be handled in an other way?
            }

        }
    }

    public function backoff()
    {
        // return [5, 10, 30, 240];
        return [rand(5, 10), rand(30, 40), rand(60, 79), rand(160, 400)];

    }

}

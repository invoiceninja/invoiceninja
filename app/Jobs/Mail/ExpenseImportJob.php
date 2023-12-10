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
use App\Helpers\Mail\IncomingMailHandler;
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

class ExpenseImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 4; //number of retries

    public $deleteWhenMissingModels = true;

    /** @var null|\App\Models\Company $company  **/
    public Company $company;

    private $expense_repo;

    public function __construct()
    {

        $this->expense_repo = new ExpenseRepository();
    }

    public function backoff()
    {
        // return [5, 10, 30, 240];
        return [rand(5, 10), rand(30, 40), rand(60, 79), rand(160, 400)];

    }

    public function handle()
    {

        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            nlog("importing expenses from imap-servers");

            $a = Account::with('companies')->cursor()->each(function ($account) {
                $account->companies()->where('expense_import', true)->whereNotNull('expense_mailbox_imap_host')->whereNotNull('expense_mailbox_imap_user')->whereNotNull('expense_mailbox_imap_password')->cursor()->each(function ($company) {
                    $this->handleCompanyImap($company);
                });
            });
        }

    }

    private function handleCompanyImap(Company $company)
    {
        $incommingMails = new IncomingMailHandler($company->expense_mailbox_imap_host, $company->company->expense_mailbox_imap_user, $company->company->expense_mailbox_imap_password);

        $emails = $incommingMails->getUnprocessedEmails();

        foreach ($emails as $mail) {

            $sender = $mail->getSender();

            $vendor = Vendor::where('expense_sender_email', $sender)->orWhere($sender, 'LIKE', "CONCAT('%',expense_sender_email)")->first();

            if ($vendor !== null)
                $vendor = Vendor::where("email", $sender)->first();

            // TODO: check email for existing vendor?!
            $data = [
                "vendor_id" => $vendor !== null ? $vendor->id : null,
                "date" => $mail->getDate(),
                "public_notes" => $mail->getSubject(),
                "private_notes" => $mail->getCompleteBodyText(),
                "documents" => $mail->getAttachments(), // FIXME: https://github.com/ddeboer/imap?tab=readme-ov-file#message-attachments
            ];

            $expense = $this->expense_repo->save($data, ExpenseFactory::create($company->company->id, $company->company->owner()->id)); // TODO: dont assign a new number at beginning

            // TODO: check for recurring expense?! => maybe replace existing ?!

            event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null)));

            event('eloquent.created: App\Models\Expense', $expense);

            $mail->markAsSeen();
            $incommingMails->moveProcessed($mail);

        }
    }

}

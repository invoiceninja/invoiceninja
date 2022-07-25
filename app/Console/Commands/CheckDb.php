<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App;
use App\Factory\ClientContactFactory;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Gateway;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\Webhook;
use App\Utils\Ninja;
use DB;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mail;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class CheckDb.
 */
class CheckDb extends Command
{
    protected $signature = 'ninja:check-db';

    protected $description = 'Check MultiDB';

    protected $log = '';

    private $entities = [
        Account::class,
        Activity::class,
        Backup::class,
        Client::class,
        ClientContact::class,
        ClientGatewayToken::class,
        Company::class,
        CompanyGateway::class,
        CompanyLedger::class,
        CompanyToken::class,
        CompanyUser::class,
        Credit::class,
        CreditInvitation::class,
        Design::class,
        Document::class,
        Expense::class,
        ExpenseCategory::class,
        Gateway::class,
        GroupSetting::class,
        Invoice::class,
        InvoiceInvitation::class,
        Payment::class,
        Paymentable::class,
        PaymentHash::class,
        Product::class,
        Project::class,
        Quote::class,
        QuoteInvitation::class,
        RecurringInvoice::class,
        RecurringInvoiceInvitation::class,
        Subscription::class,
        SystemLog::class,
        Task::class,
        TaskStatus::class,
        TaxRate::class,
        User::class,
        Vendor::class,
        VendorContact::class,
        WebHook::class,
    ];

    public function handle()
    {
        $this->LogMessage('Checking - V5_DB1');

        foreach ($this->entities as $entity) {
            $count_db_1 = $entity::on('db-ninja-01')->count();
            $count_db_2 = $entity::on('db-ninja-02a')->count();

            $diff = $count_db_1 - $count_db_2;

            if ($diff != 0) {
                $this->logMessage("{$entity} DB1: {$count_db_1} - DB2: {$count_db_2} - diff = {$diff}");
            }
        }

        $this->LogMessage('Checking - V5_DB2');

        foreach ($this->entities as $entity) {
            $count_db_1 = $entity::on('db-ninja-02')->count();
            $count_db_2 = $entity::on('db-ninja-01a')->count();

            $diff = $count_db_1 - $count_db_2;

            if ($diff != 0) {
                $this->logMessage("{$entity} DB1: {$count_db_1} - DB2: {$count_db_2} - diff = {$diff}");
            }
        }
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }
}

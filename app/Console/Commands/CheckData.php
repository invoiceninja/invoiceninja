<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App;
use App\Factory\ClientContactFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Ninja;
use DB;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mail;
use Symfony\Component\Console\Input\InputOption;

/*

##################################################################
WARNING: Please backup your database before running this script
##################################################################

If you have any questions please email us at contact@invoiceninja.com

Usage:

php artisan ninja:check-data

Options:

--client_id:<value>

    Limits the script to a single client

--fix=true

    By default the script only checks for errors, adding this option
    makes the script apply the fixes.

--fast=true

    Skip using phantomjs

*/

/**
 * Class CheckData.
 */
class CheckData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ninja:check-data {--database=} {--fix=} {--client_id=} {--paid_to_date=} {--client_balance=}';

    /**
     * @var string
     */
    protected $description = 'Check/fix data';

    protected $log = '';

    protected $isValid = true;

    protected $wrong_paid_to_dates = 0;

    protected $wrong_balances = 0;

    public function handle()
    {
        $time_start = microtime(true); 

        $database_connection = $this->option('database') ? $this->option('database') : 'Connected to Default DB';
        $fix_status = $this->option('fix') ? "Fixing Issues" : "Just checking issues ";

        $this->logMessage(date('Y-m-d h:i:s').' Running CheckData... on ' . $database_connection . " Fix Status = {$fix_status}");

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        $this->checkInvoiceBalances();
        $this->checkInvoicePayments();
        $this->checkPaidToDates();
        // $this->checkPaidToCompanyDates();
        $this->checkClientBalances();
        $this->checkContacts();
        $this->checkCompanyData();


        if (! $this->option('client_id')) {
            $this->checkOAuth();
            //$this->checkFailedJobs();
        }

        $this->logMessage('Done: '.strtoupper($this->isValid ? Account::RESULT_SUCCESS : Account::RESULT_FAILURE));
        $this->logMessage('Total execution time in seconds: ' . (microtime(true) - $time_start));

        $errorEmail = config('ninja.error_email');

        if ($errorEmail) {
            Mail::raw($this->log, function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->subject('Check-Data: '.strtoupper($this->isValid ? Account::RESULT_SUCCESS : Account::RESULT_FAILURE)." [{$database}]");
            });
        } elseif (! $this->isValid) {
            new Exception("Check data failed!!\n".$this->log);
        }
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }

    private function checkOAuth()
    {
        // check for duplicate oauth ids
        $users = DB::table('users')
                    ->whereNotNull('oauth_user_id')
                    ->groupBy('users.oauth_user_id')
                    ->havingRaw('count(users.id) > 1')
                    ->get(['users.oauth_user_id']);

        $this->logMessage($users->count().' users with duplicate oauth ids');

        if ($users->count() > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($users as $user) {
                $first = true;
                $this->logMessage('checking '.$user->oauth_user_id);
                $matches = DB::table('users')
                            ->where('oauth_user_id', '=', $user->oauth_user_id)
                            ->orderBy('id')
                            ->get(['id']);

                foreach ($matches as $match) {
                    if ($first) {
                        $this->logMessage('skipping '.$match->id);
                        $first = false;
                        continue;
                    }
                    $this->logMessage('updating '.$match->id);

                    DB::table('users')
                        ->where('id', '=', $match->id)
                        ->where('oauth_user_id', '=', $user->oauth_user_id)
                        ->update([
                            'oauth_user_id' => null,
                            'oauth_provider_id' => null,
                        ]);
                }
            }
        }
    }

    private function checkContacts()
    {
        // check for contacts with the contact_key value set
        $contacts = DB::table('client_contacts')
                        ->whereNull('contact_key')
                        ->orderBy('id')
                        ->get(['id']);
        $this->logMessage($contacts->count().' contacts without a contact_key');

        if ($contacts->count() > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($contacts as $contact) {
                DB::table('client_contacts')
                    ->where('id', '=', $contact->id)
                    ->whereNull('contact_key')
                    ->update([
                        'contact_key' => str_random(config('ninja.key_length')),
                    ]);
            }
        }

        // check for missing contacts
        $clients = DB::table('clients')
                    ->leftJoin('client_contacts', function ($join) {
                        $join->on('client_contacts.client_id', '=', 'clients.id')
                            ->whereNull('client_contacts.deleted_at');
                    })
                    ->groupBy('clients.id', 'clients.user_id', 'clients.company_id')
                    ->havingRaw('count(client_contacts.id) = 0');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        }

        $clients = $clients->get(['clients.id', 'clients.user_id', 'clients.company_id']);
        $this->logMessage($clients->count().' clients without any contacts');

        if ($clients->count() > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($clients as $client) {
                $this->logMessage("Fixing missing contacts #{$client->id}");
                
                $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
                $new_contact->client_id = $client->id;
                $new_contact->contact_key = Str::random(40);
                $new_contact->is_primary = true;
                $new_contact->save();
            }
        }

        // // check for more than one primary contact
        // $clients = DB::table('clients')
        //             ->leftJoin('client_contacts', function ($join) {
        //                 $join->on('client_contacts.client_id', '=', 'clients.id')
        //                     ->where('client_contacts.is_primary', '=', true)
        //                     ->whereNull('client_contacts.deleted_at');
        //             })
        //             ->groupBy('clients.id')
        //             ->havingRaw('count(client_contacts.id) != 1');

        // if ($this->option('client_id')) {
        //     $clients->where('clients.id', '=', $this->option('client_id'));
        // }

        // $clients = $clients->get(['clients.id', 'clients.user_id', 'clients.company_id']);
        // // $this->logMessage($clients->count().' clients without a single primary contact');

        // // if ($this->option('fix') == 'true') {
        // //     foreach ($clients as $client) {
        // //         $this->logMessage("Fixing missing primary contacts #{$client->id}");
                
        // //         $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
        // //         $new_contact->client_id = $client->id;
        // //         $new_contact->contact_key = Str::random(40);
        // //         $new_contact->is_primary = true;
        // //         $new_contact->save();
        // //     }
        // // }

        // if ($clients->count() > 0) {
        //     $this->isValid = false;
        // }
    }

    private function checkFailedJobs()
    {
        if (config('ninja.testvars.travis')) {
            return;
        }

        $queueDB = config('queue.connections.database.connection');
        $count = DB::connection($queueDB)->table('failed_jobs')->count();

        if ($count > 25) {
            $this->isValid = false;
        }

        $this->logMessage($count.' failed jobs');
    }

    private function checkInvitations()
    {
        $invoices = DB::table('invoices')
                    ->leftJoin('invoice_invitations', function ($join) {
                        $join->on('invoice_invitations.invoice_id', '=', 'invoices.id')
                             ->whereNull('invoice_invitations.deleted_at');
                    })
                    ->groupBy('invoices.id', 'invoices.user_id', 'invoices.company_id', 'invoices.client_id')
                    ->havingRaw('count(invoice_invitations.id) = 0')
                    ->get(['invoices.id', 'invoices.user_id', 'invoices.company_id', 'invoices.client_id']);

        $this->logMessage($invoices->count().' invoices without any invitations');

        if ($invoices->count() > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($invoices as $invoice) {
                $invitation = new InvoiceInvitation();
                $invitation->company_id = $invoice->company_id;
                $invitation->user_id = $invoice->user_id;
                $invitation->invoice_id = $invoice->id;
                $invitation->contact_id = ClientContact::whereClientId($invoice->client_id)->whereIsPrimary(true)->first()->id;
                $invitation->invitation_key = str_random(config('ninja.key_length'));
                $invitation->save();
            }
        }
    }

    // private function checkPaidToCompanyDates()
    // {
    //     Company::cursor()->each(function ($company){

    //     $payments = Payment::where('is_deleted', 0)
    //                        ->where('company_id', $company->id)
    //                        ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED])
    //                        ->pluck('id');

    //     $unapplied = Payment::where('is_deleted', 0)
    //                         ->where('company_id', $company->id)
    //                         ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
    //                         ->sum(\DB::Raw('amount - applied'));

    //     $paymentables = Paymentable::whereIn('payment_id', $payments)->sum(\DB::Raw('amount - refunded'));

    //     $client_paid_to_date = Client::where('company_id', $company->id)->where('is_deleted', 0)->withTrashed()->sum('paid_to_date');

    //     $total_payments = $paymentables + $unapplied;

    //      if (round($total_payments, 2) != round($client_paid_to_date, 2)) {
    //             $this->wrong_paid_to_dates++;

    //             $this->logMessage($company->present()->name.' id = # '.$company->id." - Paid to date does not match Client Paid To Date = {$client_paid_to_date} - Invoice Payments = {$total_payments}");
    //         }

    //     });

    // }

    private function checkPaidToDates()
    {
        $this->wrong_paid_to_dates = 0;
        $credit_total_applied = 0;


        $clients = DB::table('clients')
                    ->leftJoin('payments', function($join) {
                        $join->on('payments.client_id', '=', 'clients.id')
                            ->where('payments.is_deleted', 0)
                            ->whereIn('payments.status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED]);
                    })
                    ->where('clients.is_deleted',0)
                    ->where('clients.updated_at', '>', now()->subDays(2))
                    ->groupBy('clients.id')
                    ->havingRaw('clients.paid_to_date != sum(coalesce(payments.amount - payments.refunded, 0))')
                    ->get(['clients.id', 'clients.paid_to_date', DB::raw('sum(coalesce(payments.amount - payments.refunded, 0)) as amount')]);

        /* Due to accounting differences we need to perform a second loop here to ensure there actually is an issue */
        $clients->each(function ($client_record) use ($credit_total_applied) {
            
            $client = Client::withTrashed()->find($client_record->id);

            $total_invoice_payments = 0;

            foreach ($client->invoices()->where('is_deleted', false)->where('status_id', '>', 1)->get() as $invoice) {

                $total_invoice_payments += $invoice->payments()
                                                    ->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                                                    ->selectRaw('sum(paymentables.amount - paymentables.refunded) as p')
                                                    ->pluck('p')
                                                    ->first();

            }

            //commented IN 27/06/2021 - sums ALL client payments AND the unapplied amounts to match the client paid to date
            $p = Payment::where('client_id', $client->id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
            ->sum(DB::Raw('amount - applied'));

            $total_invoice_payments += $p;

            // 10/02/21
            foreach ($client->payments as $payment) {

                $credit_total_applied += $payment->paymentables()
                                                ->where('paymentable_type', App\Models\Credit::class)
                                                ->selectRaw('sum(paymentables.amount - paymentables.refunded) as p')
                                                ->pluck('p')
                                                ->first();
            }

            if ($credit_total_applied < 0) {
                $total_invoice_payments += $credit_total_applied;
            } 

            if (round($total_invoice_payments, 2) != round($client->paid_to_date, 2)) {
                $this->wrong_paid_to_dates++;

                $this->logMessage($client->present()->name.' id = # '.$client->id." - Paid to date does not match Client Paid To Date = {$client->paid_to_date} - Invoice Payments = {$total_invoice_payments}");

                $this->isValid = false;

                if($this->option('paid_to_date')){
                    $this->logMessage("# {$client->id} " . $client->present()->name.' - '.$client->number." Fixing {$client->paid_to_date} to {$total_invoice_payments}");
                    $client->paid_to_date = $total_invoice_payments;
                    $client->save();
                }
            }
        });

        $this->logMessage("{$this->wrong_paid_to_dates} clients with incorrect paid to dates");
    }

    private function checkInvoicePayments()
    {
        $this->wrong_balances = 0;

        Client::cursor()->where('is_deleted', 0)->where('clients.updated_at', '>', now()->subDays(2))->each(function ($client) {
            
            $client->invoices->where('is_deleted', false)->whereIn('status_id', '!=', Invoice::STATUS_DRAFT)->each(function ($invoice) use ($client) {

                $total_paid = $invoice->payments()
                                    ->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                                    ->selectRaw('sum(paymentables.amount - paymentables.refunded) as p')
                                    ->pluck('p')
                                    ->first();

                // $total_paid = $total_amount - $total_refund;

                $total_credit = $invoice->credits()->get()->sum('amount');

                $calculated_paid_amount = $invoice->amount - $invoice->balance - $total_credit;

                if ((string)$total_paid != (string)($invoice->amount - $invoice->balance - $total_credit)) {
                    $this->wrong_balances++;

                    $this->logMessage($client->present()->name.' - '.$client->id." - Total Paid = {$total_paid} != Calculated Total = {$calculated_paid_amount}");

                    $this->isValid = false;
                }
            });
            
        });

        $this->logMessage("{$this->wrong_balances} clients with incorrect invoice balances");
    }



        // $clients = DB::table('clients')
        //             ->leftJoin('invoices', function($join){
        //                 $join->on('invoices.client_id', '=', 'clients.id')
        //                      ->where('invoices.is_deleted',0)
        //                      ->where('invoices.status_id', '>', 1);
        //             })
        //             ->leftJoin('credits', function($join){
        //                 $join->on('credits.client_id', '=', 'clients.id')
        //                      ->where('credits.is_deleted',0)
        //                      ->where('credits.status_id', '>', 1);
        //             })
        //             ->leftJoin('payments', function($join) {
        //                 $join->on('payments.client_id', '=', 'clients.id')
        //                     ->where('payments.is_deleted', 0)
        //                     ->whereIn('payments.status_id', [Payment::STATUS_COMPLETED, Payment:: STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED]);
        //             })
        //             ->where('clients.is_deleted',0)
        //             //->where('clients.updated_at', '>', now()->subDays(2))
        //             ->groupBy('clients.id')
        //             ->havingRaw('sum(coalesce(invoices.amount - invoices.balance - credits.amount)) != sum(coalesce(payments.amount - payments.refunded, 0))')
        //             ->get(['clients.id', DB::raw('sum(coalesce(invoices.amount - invoices.balance - credits.amount)) as invoice_amount'), DB::raw('sum(coalesce(payments.amount - payments.refunded, 0)) as payment_amount')]);









    private function checkClientBalances()
    {
        $this->wrong_balances = 0;
        $this->wrong_paid_to_dates = 0;

        foreach (Client::cursor()->where('is_deleted', 0)->where('clients.updated_at', '>', now()->subDays(2)) as $client) {
            //$invoice_balance = $client->invoices->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');
            $invoice_balance = Invoice::where('client_id', $client->id)->where('is_deleted', false)->where('status_id', '>', 1)->withTrashed()->sum('balance');
            $credit_balance = Credit::where('client_id', $client->id)->where('is_deleted', false)->withTrashed()->sum('balance');

            /*Legacy - V4 will add credits to the balance - we may need to reverse engineer this and remove the credits from the client balance otherwise we need this hack here and in the invoice balance check.*/
            if($client->balance != $invoice_balance)
                $invoice_balance -= $credit_balance;

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && (string) $invoice_balance != (string) $client->balance) {
                $this->wrong_paid_to_dates++;
                $this->logMessage($client->present()->name.' - '.$client->id." - calculated client balances do not match Invoice Balances = {$invoice_balance} - Client Balance = ".rtrim($client->balance, '0'). " Ledger balance = {$ledger->balance}");

                $this->isValid = false;

            }
        }

        $this->logMessage("{$this->wrong_paid_to_dates} clients with incorrect client balances");
    }

    //fix for client balances = 
    //$adjustment = ($invoice_balance-$client->balance)
    //$client->balance += $adjustment;

    //$ledger_adjustment = $ledger->balance - $client->balance;
    //$ledger->balance += $ledger_adjustment

    private function checkInvoiceBalances()
    {
        $this->wrong_balances = 0;
        $this->wrong_paid_to_dates = 0;

        foreach (Client::where('is_deleted', 0)->where('clients.updated_at', '>', now()->subDays(2))->cursor() as $client) {
            $invoice_balance = $client->invoices()->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');
            $credit_balance = $client->credits()->where('is_deleted', false)->sum('balance');

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && number_format($invoice_balance, 4) != number_format($client->balance, 4)) {
                $this->wrong_balances++;
                $this->logMessage("# {$client->id} " . $client->present()->name.' - '.$client->number." - Balance Failure - Invoice Balances = {$invoice_balance} Client Balance = {$client->balance} Ledger Balance = {$ledger->balance}");

                $this->isValid = false;


                if($this->option('client_balance')){
                    
                    $this->logMessage("# {$client->id} " . $client->present()->name.' - '.$client->number." Fixing {$client->balance} to {$invoice_balance}");
                    $client->balance = $invoice_balance;
                    $client->save();

                    $ledger->adjustment = $invoice_balance;
                    $ledger->balance = $invoice_balance;
                    $ledger->notes = 'Ledger Adjustment';
                    $ledger->save();
                }
                
            }
        }

        $this->logMessage("{$this->wrong_balances} clients with incorrect balances");
    }

    private function checkLogoFiles()
    {
        // $accounts = DB::table('accounts')
        //             ->where('logo', '!=', '')
        //             ->orderBy('id')
        //             ->get(['logo']);

        // $countMissing = 0;

        // foreach ($accounts as $account) {
        //     $path = public_path('logo/' . $account->logo);
        //     if (! file_exists($path)) {
        //         $this->logMessage('Missing file: ' . $account->logo);
        //         $countMissing++;
        //     }
        // }

        // if ($countMissing > 0) {
        //     $this->isValid = false;
        // }

        // $this->logMessage($countMissing . ' missing logo files');
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fix', null, InputOption::VALUE_OPTIONAL, 'Fix data', null],
            ['fast', null, InputOption::VALUE_OPTIONAL, 'Fast', null],
            ['client_id', null, InputOption::VALUE_OPTIONAL, 'Client id', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }

    private function checkCompanyData()
    {
        $tables = [
            'activities' => [
                'invoice',
                'client',
                'client_contact',
                'payment',
                'recurring_invoice',
            ],
            'invoices' => [
                'client',
            ],
            'payments' => [
                'client',
            ],
            'products' => [

            ],
        ];

        foreach ($tables as $table => $entityTypes) {
            foreach ($entityTypes as $entityType) {
                $tableName = $this->pluralizeEntityType($entityType);
                $field = $entityType;
                if ($table == 'companies') {
                    $company_id = 'id';
                } else {
                    $company_id = 'company_id';
                }
                $records = DB::table($table)
                                ->join($tableName, "{$tableName}.id", '=', "{$table}.{$field}_id")
                                ->where("{$table}.{$company_id}", '!=', DB::raw("{$tableName}.company_id"))
                                ->get(["{$table}.id"]);

                if ($records->count()) {
                    $this->isValid = false;
                    $this->logMessage($records->count()." {$table} records with incorrect {$entityType} company id");
                }
            }
        }

        // foreach(User::cursor() as $user) {

        //     $records = Company::where('account_id',)

        // }
    }

    public function pluralizeEntityType($type)
    {
        if ($type === 'company') {
            return 'companies';
        }

        return $type.'s';
    }
}

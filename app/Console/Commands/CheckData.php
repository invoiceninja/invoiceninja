<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands;

use App;
use App\Libraries\CurlUtils;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyLedger;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Ninja;
use Carbon;
use DB;
use Exception;
use Illuminate\Console\Command;
use Mail;
use Symfony\Component\Console\Input\InputOption;
use Utils;

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
    protected $name = 'ninja:check-data';

    /**
     * @var string
     */
    protected $description = 'Check/fix data';

    protected $log = '';
    protected $isValid = true;

    public function handle()
    {
        $this->logMessage(date('Y-m-d h:i:s').' Running CheckData...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        $this->checkInvoiceBalances();
        $this->checkInvoicePayments();
        $this->checkPaidToDates();
        $this->checkClientBalances();
        $this->checkContacts();
        $this->checkCompanyData();

        //$this->checkLogoFiles();

        if (! $this->option('client_id')) {
            $this->checkOAuth();
            //$this->checkInvitations();

            $this->checkFailedJobs();
        }

        $this->logMessage('Done: '.strtoupper($this->isValid ? Account::RESULT_SUCCESS : Account::RESULT_FAILURE));
        $errorEmail = config('ninja.error_email');

        if ($errorEmail) {
            Mail::raw($this->log, function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(config('ninja.error_email'))
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
                $contact = new ClientContact();
                $contact->company_id = $client->company_id;
                $contact->user_id = $client->user_id;
                $contact->client_id = $client->id;
                $contact->is_primary = true;
                $contact->send_invoice = true;
                $contact->contact_key = str_random(config('ninja.key_length'));
                $contact->save();
            }
        }

        // check for more than one primary contact
        $clients = DB::table('clients')
                    ->leftJoin('client_contacts', function ($join) {
                        $join->on('client_contacts.client_id', '=', 'clients.id')
                            ->where('client_contacts.is_primary', '=', true)
                            ->whereNull('client_contacts.deleted_at');
                    })
                    ->groupBy('clients.id')
                    ->havingRaw('count(client_contacts.id) != 1');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        }

        $clients = $clients->get(['clients.id', DB::raw('count(client_contacts.id)')]);
        $this->logMessage($clients->count().' clients without a single primary contact');

        if ($clients->count() > 0) {
            $this->isValid = false;
        }
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

    private function checkInvoiceBalances()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        foreach (Client::cursor() as $client) {
            $invoice_balance = $client->invoices->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && number_format($invoice_balance, 4) != number_format($client->balance, 4)) {
                $wrong_balances++;
                $this->logMessage($client->present()->name.' - '.$client->number." - Balance Failure - Invoice Balances = {$invoice_balance} Client Balance = {$client->balance} Ledger Balance = {$ledger->balance}");

                $this->isValid = false;
            }
        }

        $this->logMessage("{$wrong_balances} clients with incorrect balances");
    }

    private function checkPaidToDates()
    {
        $wrong_paid_to_dates = 0;
        $credit_total_applied = 0;

        Client::withTrashed()->cursor()->each(function ($client) use ($wrong_paid_to_dates, $credit_total_applied) {
            $total_invoice_payments = 0;

            foreach ($client->invoices->where('is_deleted', false) as $invoice) {
                $total_amount = $invoice->payments->whereNull('deleted_at')->sum('pivot.amount');
                $total_refund = $invoice->payments->whereNull('deleted_at')->sum('pivot.refunded');

                 $total_invoice_payments += ($total_amount - $total_refund);
            }

            foreach($client->payments as $payment)
            {
              $credit_total_applied += $payment->paymentables->where('paymentable_type', App\Models\Credit::class)->sum(DB::raw('amount'));
            }

            if($credit_total_applied < 0)
                $total_invoice_payments += $credit_total_applied; //todo this is contentious

            info("total invoice payments = {$total_invoice_payments} with client paid to date of of {$client->paid_to_date}");

            if (round($total_invoice_payments, 2) != round($client->paid_to_date, 2)) {
                $wrong_paid_to_dates++;

                $this->logMessage($client->present()->name.' - '.$client->id." - Paid to date does not match Client Paid To Date = {$client->paid_to_date} - Invoice Payments = {$total_invoice_payments}");

                $this->isValid = false;
            }
        });

        $this->logMessage("{$wrong_paid_to_dates} clients with incorrect paid to dates");
    }

    private function checkInvoicePayments()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        Client::cursor()->each(function ($client) use ($wrong_balances) {
            $client->invoices->where('is_deleted', false)->whereIn('status_id', '!=', Invoice::STATUS_DRAFT)->each(function ($invoice) use ($wrong_balances, $client) {
                $total_amount = $invoice->payments->sum('pivot.amount');
                $total_refund = $invoice->payments->sum('pivot.refunded');
                $total_credit = $invoice->credits->sum('amount');

                $total_paid = $total_amount - $total_refund;
                $calculated_paid_amount = $invoice->amount - $invoice->balance - $total_credit;

                if ((string)$total_paid != (string)($invoice->amount - $invoice->balance - $total_credit)) {
                    $wrong_balances++;

                    $this->logMessage($client->present()->name.' - '.$client->id." - Total Amount = {$total_amount} != Calculated Total = {$calculated_paid_amount} - Total Refund = {$total_refund} Total credit = {$total_credit}");

                    $this->isValid = false;
                }
            });
        });

        $this->logMessage("{$wrong_balances} clients with incorrect invoice balances");
    }

    private function checkClientBalances()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        foreach (Client::cursor() as $client) {
            $invoice_balance = $client->invoices->sum('balance');
            // $invoice_amounts = $client->invoices->sum('amount') - $invoice_balance;

            // $credit_amounts = 0;

            // foreach ($client->invoices as $invoice) {
            //     $credit_amounts += $invoice->credits->sum('amount');
            // }

            // /*To handle invoice reversals, we need to "ADD BACK" the credit amounts here*/
            // $client_paid_to_date = $client->paid_to_date + $credit_amounts;

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && (string) $invoice_balance != (string) $client->balance) {
                $wrong_paid_to_dates++;
                $this->logMessage($client->present()->name.' - '.$client->id." - client paid to dates do not match {$invoice_balance} - ".rtrim($client->balance, '0'));

                $this->isValid = false;
            }
        }

        $this->logMessage("{$wrong_paid_to_dates} clients with incorrect paid_to_dates");
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

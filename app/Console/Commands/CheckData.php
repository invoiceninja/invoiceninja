<?php

namespace App\Console\Commands;

use Carbon;
use DB;
use Exception;
use Illuminate\Console\Command;
use Mail;
use Symfony\Component\Console\Input\InputOption;
use Utils;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Invitation;

/*

##################################################################
WARNING: Please backup your database before running this script
##################################################################

Since the application was released a number of bugs have inevitably been found.
Although the bugs have always been fixed in some cases they've caused the client's
balance, paid to date and/or activity records to become inaccurate. This script will
check for errors and correct the data.

If you have any questions please email us at contact@invoiceninja.com

Usage:

php artisan ninja:check-data

Options:

--client_id:<value>

    Limits the script to a single client

--fix=true

    By default the script only checks for errors, adding this option
    makes the script apply the fixes.

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

    public function fire()
    {
        $this->logMessage(date('Y-m-d') . ' Running CheckData...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        if (! $this->option('client_id')) {
            $this->checkBlankInvoiceHistory();
            $this->checkPaidToDate();
            $this->checkDraftSentInvoices();
        }

        $this->checkBalances();
        $this->checkContacts();
        $this->checkUserAccounts();

        if (! $this->option('client_id')) {
            $this->checkOAuth();
            $this->checkInvitations();
            $this->checkFailedJobs();
            $this->checkAccountData();
            $this->checkLookupData();
        }

        $this->logMessage('Done: ' . strtoupper($this->isValid ? RESULT_SUCCESS : RESULT_FAILURE));
        $errorEmail = env('ERROR_EMAIL');

        if ($errorEmail) {
            Mail::raw($this->log, function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject("Check-Data [{$database}]: " . strtoupper($this->isValid ? RESULT_SUCCESS : RESULT_FAILURE));
            });
        } elseif (! $this->isValid) {
            throw new Exception('Check data failed!!');
        }
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s') . ' ' . $str;
        $this->info($str);
        $this->log .= $str . "\n";
    }

    private function checkDraftSentInvoices()
    {
        $invoices = Invoice::whereInvoiceStatusId(INVOICE_STATUS_SENT)
                        ->whereIsPublic(false)
                        ->withTrashed()
                        ->get();

        $this->logMessage(count($invoices) . ' draft sent invoices');

        if (count($invoices) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($invoices as $invoice) {
                if ($invoice->is_deleted) {
                    $invoice->unsetEventDispatcher();
                }
                $invoice->markSent();
            }
        }
    }

    private function checkOAuth()
    {
        // check for duplicate oauth ids
        $users = DB::table('users')
                    ->whereNotNull('oauth_user_id')
                    ->groupBy('users.oauth_user_id')
                    ->havingRaw('count(users.id) > 1')
                    ->get(['users.oauth_user_id']);

        $this->logMessage(count($users) . ' users with duplicate oauth ids');

        if (count($users) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($users as $user) {
                $first = true;
                $this->logMessage('checking ' . $user->oauth_user_id);
                $matches = DB::table('users')
                            ->where('oauth_user_id', '=', $user->oauth_user_id)
                            ->orderBy('id')
                            ->get(['id']);

                foreach ($matches as $match) {
                    if ($first) {
                        $this->logMessage('skipping ' . $match->id);
                        $first = false;
                        continue;
                    }
                    $this->logMessage('updating ' . $match->id);

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

    private function checkLookupData()
    {
        $tables = [
            'account_tokens',
            'accounts',
            'companies',
            'contacts',
            'invitations',
            'users',
        ];

        foreach ($tables as $table) {
            $count = DB::table('lookup_' . $table)->count();
            if ($count > 0) {
                $this->logMessage("Lookup table {$table} has {$count} records");
                $this->isValid = false;
            }
        }
    }

    private function checkUserAccounts()
    {
        $userAccounts = DB::table('user_accounts')
                        ->leftJoin('users as u1', 'u1.id', '=', 'user_accounts.user_id1')
                        ->leftJoin('accounts as a1', 'a1.id', '=', 'u1.account_id')
                        ->leftJoin('users as u2', 'u2.id', '=', 'user_accounts.user_id2')
                        ->leftJoin('accounts as a2', 'a2.id', '=', 'u2.account_id')
                        ->leftJoin('users as u3', 'u3.id', '=', 'user_accounts.user_id3')
                        ->leftJoin('accounts as a3', 'a3.id', '=', 'u3.account_id')
                        ->leftJoin('users as u4', 'u4.id', '=', 'user_accounts.user_id4')
                        ->leftJoin('accounts as a4', 'a4.id', '=', 'u4.account_id')
                        ->leftJoin('users as u5', 'u5.id', '=', 'user_accounts.user_id5')
                        ->leftJoin('accounts as a5', 'a5.id', '=', 'u5.account_id')
                        ->get([
                            'user_accounts.id',
                            'a1.company_id as a1_company_id',
                            'a2.company_id as a2_company_id',
                            'a3.company_id as a3_company_id',
                            'a4.company_id as a4_company_id',
                            'a5.company_id as a5_company_id',
                        ]);

        $countInvalid = 0;

        foreach ($userAccounts as $userAccount) {
            $ids = [];

            if ($companyId1 = $userAccount->a1_company_id) {
                $ids[$companyId1] = true;
            }
            if ($companyId2 = $userAccount->a2_company_id) {
                $ids[$companyId2] = true;
            }
            if ($companyId3 = $userAccount->a3_company_id) {
                $ids[$companyId3] = true;
            }
            if ($companyId4 = $userAccount->a4_company_id) {
                $ids[$companyId4] = true;
            }
            if ($companyId5 = $userAccount->a5_company_id) {
                $ids[$companyId5] = true;
            }

            if (count($ids) > 1) {
                $this->info('user_account: ' . $userAccount->id);
                $countInvalid++;
            }
        }

        $this->logMessage($countInvalid . ' user accounts with multiple companies');

        if ($countInvalid > 0) {
            $this->isValid = false;
        }
    }

    private function checkContacts()
    {
        // check for contacts with the contact_key value set
        $contacts = DB::table('contacts')
                        ->whereNull('contact_key')
                        ->orderBy('id')
                        ->get(['id']);
        $this->logMessage(count($contacts) . ' contacts without a contact_key');

        if (count($contacts) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($contacts as $contact) {
                DB::table('contacts')
                    ->where('id', '=', $contact->id)
                    ->whereNull('contact_key')
                    ->update([
                        'contact_key' => strtolower(str_random(RANDOM_KEY_LENGTH)),
                    ]);
            }
        }

        // check for missing contacts
        $clients = DB::table('clients')
                    ->leftJoin('contacts', function($join) {
                        $join->on('contacts.client_id', '=', 'clients.id')
                            ->whereNull('contacts.deleted_at');
                    })
                    ->groupBy('clients.id', 'clients.user_id', 'clients.account_id')
                    ->havingRaw('count(contacts.id) = 0');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        }

        $clients = $clients->get(['clients.id', 'clients.user_id', 'clients.account_id']);
        $this->logMessage(count($clients) . ' clients without any contacts');

        if (count($clients) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($clients as $client) {
                $contact = new Contact();
                $contact->account_id = $client->account_id;
                $contact->user_id = $client->user_id;
                $contact->client_id = $client->id;
                $contact->is_primary = true;
                $contact->send_invoice = true;
                $contact->contact_key = strtolower(str_random(RANDOM_KEY_LENGTH));
                $contact->public_id = Contact::whereAccountId($client->account_id)->withTrashed()->max('public_id') + 1;
                $contact->save();
            }
        }

        // check for more than one primary contact
        $clients = DB::table('clients')
                    ->leftJoin('contacts', function($join) {
                        $join->on('contacts.client_id', '=', 'clients.id')
                            ->where('contacts.is_primary', '=', true)
                            ->whereNull('contacts.deleted_at');
                    })
                    ->groupBy('clients.id')
                    ->havingRaw('count(contacts.id) != 1');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        }

        $clients = $clients->get(['clients.id', DB::raw('count(contacts.id)')]);
        $this->logMessage(count($clients) . ' clients without a single primary contact');

        if (count($clients) > 0) {
            $this->isValid = false;
        }
    }

    private function checkFailedJobs()
    {
        $count = DB::table('failed_jobs')->count();

        if ($count > 0) {
            $this->isValid = false;
        }

        $this->logMessage($count . ' failed jobs');
    }

    private function checkBlankInvoiceHistory()
    {
        $count = DB::table('activities')
                    ->where('activity_type_id', '=', 5)
                    ->where('json_backup', '=', '')
                    ->where('id', '>', 858720)
                    ->count();

        if ($count > 0) {
            $this->isValid = false;
        }

        $this->logMessage($count . ' activities with blank invoice backup');
    }

    private function checkInvitations()
    {
        $invoices = DB::table('invoices')
                    ->leftJoin('invitations', function ($join) {
                        $join->on('invitations.invoice_id', '=', 'invoices.id')
                             ->whereNull('invitations.deleted_at');
                    })
                    ->groupBy('invoices.id', 'invoices.user_id', 'invoices.account_id', 'invoices.client_id')
                    ->havingRaw('count(invitations.id) = 0')
                    ->get(['invoices.id', 'invoices.user_id', 'invoices.account_id', 'invoices.client_id']);

        $this->logMessage(count($invoices) . ' invoices without any invitations');

        if (count($invoices) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($invoices as $invoice) {
                $invitation = new Invitation();
                $invitation->account_id = $invoice->account_id;
                $invitation->user_id = $invoice->user_id;
                $invitation->invoice_id = $invoice->id;
                $invitation->contact_id = Contact::whereClientId($invoice->client_id)->whereIsPrimary(true)->first()->id;
                $invitation->invitation_key = strtolower(str_random(RANDOM_KEY_LENGTH));
                $invitation->public_id = Invitation::whereAccountId($invoice->account_id)->withTrashed()->max('public_id') + 1;
                $invitation->save();
            }
        }
    }

    private function checkAccountData()
    {
        $tables = [
            'activities' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_CONTACT,
                ENTITY_PAYMENT,
                ENTITY_INVITATION,
                ENTITY_USER,
            ],
            'invoices' => [
                ENTITY_CLIENT,
                ENTITY_USER,
            ],
            'payments' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_USER,
                ENTITY_INVITATION,
                ENTITY_CONTACT,
            ],
            'tasks' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_USER,
            ],
            'credits' => [
                ENTITY_CLIENT,
                ENTITY_USER,
            ],
            'expenses' => [
                ENTITY_CLIENT,
                ENTITY_VENDOR,
                ENTITY_INVOICE,
                ENTITY_USER,
            ],
            'products' => [
                ENTITY_USER,
            ],
            'vendors' => [
                ENTITY_USER,
            ],
            'expense_categories' => [
                ENTITY_USER,
            ],
            'payment_terms' => [
                ENTITY_USER,
            ],
            'projects' => [
                ENTITY_USER,
                ENTITY_CLIENT,
            ],
        ];

        foreach ($tables as $table => $entityTypes) {
            foreach ($entityTypes as $entityType) {
                $tableName = Utils::pluralizeEntityType($entityType);
                $field = $entityType;
                if ($table == 'accounts') {
                    $accountId = 'id';
                } else {
                    $accountId = 'account_id';
                }
                $records = DB::table($table)
                                ->join($tableName, "{$tableName}.id", '=', "{$table}.{$field}_id")
                                ->where("{$table}.{$accountId}", '!=', DB::raw("{$tableName}.account_id"))
                                ->get(["{$table}.id"]);

                if (count($records)) {
                    $this->isValid = false;
                    $this->logMessage(count($records) . " {$table} records with incorrect {$entityType} account id");

                    if ($this->option('fix') == 'true') {
                        foreach ($records as $record) {
                            DB::table($table)
                                ->where('id', $record->id)
                                ->update([
                                    'account_id' => $record->account_id,
                                    'user_id' => $record->user_id,
                                ]);
                        }
                    }
                }
            }
        }
    }

    private function checkPaidToDate()
    {
        // update client paid_to_date value
        $clients = DB::table('clients')
                    ->join('payments', 'payments.client_id', '=', 'clients.id')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.is_deleted', '=', 0)
                    ->where('payments.payment_status_id', '!=', 2)
                    ->where('payments.payment_status_id', '!=', 3)
                    ->where('invoices.is_deleted', '=', 0)
                    ->groupBy('clients.id')
                    ->havingRaw('clients.paid_to_date != sum(payments.amount - payments.refunded) and clients.paid_to_date != 999999999.9999')
                    ->get(['clients.id', 'clients.paid_to_date', DB::raw('sum(payments.amount) as amount')]);
        $this->logMessage(count($clients) . ' clients with incorrect paid to date');

        if (count($clients) > 0) {
            $this->isValid = false;
        }

        if ($this->option('fix') == 'true') {
            foreach ($clients as $client) {
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update(['paid_to_date' => $client->amount]);
            }
        }
    }

    private function checkBalances()
    {
        // find all clients where the balance doesn't equal the sum of the outstanding invoices
        $clients = DB::table('clients')
                    ->join('invoices', 'invoices.client_id', '=', 'clients.id')
                    ->join('accounts', 'accounts.id', '=', 'clients.account_id')
                    ->where('accounts.id', '!=', 20432)
                    ->where('clients.is_deleted', '=', 0)
                    ->where('invoices.is_deleted', '=', 0)
                    ->where('invoices.is_public', '=', 1)
                    ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                    ->where('invoices.is_recurring', '=', 0)
                    ->havingRaw('abs(clients.balance - sum(invoices.balance)) > .01 and clients.balance != 999999999.9999');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        }

        $clients = $clients->groupBy('clients.id', 'clients.balance')
                ->orderBy('accounts.company_id', 'DESC')
                ->get(['accounts.company_id', 'clients.account_id', 'clients.id', 'clients.balance', 'clients.paid_to_date', DB::raw('sum(invoices.balance) actual_balance')]);
        $this->logMessage(count($clients) . ' clients with incorrect balance/activities');

        if (count($clients) > 0) {
            $this->isValid = false;
        }

        foreach ($clients as $client) {
            $this->logMessage("=== Company: {$client->company_id} Account:{$client->account_id} Client:{$client->id} Balance:{$client->balance} Actual Balance:{$client->actual_balance} ===");
            $foundProblem = false;
            $lastBalance = 0;
            $lastAdjustment = 0;
            $lastCreatedAt = null;
            $clientFix = false;
            $activities = DB::table('activities')
                        ->where('client_id', '=', $client->id)
                        ->orderBy('activities.id')
                        ->get(['activities.id', 'activities.created_at', 'activities.activity_type_id', 'activities.adjustment', 'activities.balance', 'activities.invoice_id']);
            //$this->logMessage(var_dump($activities));

            foreach ($activities as $activity) {
                $activityFix = false;

                if ($activity->invoice_id) {
                    $invoice = DB::table('invoices')
                                ->where('id', '=', $activity->invoice_id)
                                ->first(['invoices.amount', 'invoices.is_recurring', 'invoices.invoice_type_id', 'invoices.deleted_at', 'invoices.id', 'invoices.is_deleted']);

                    // Check if this invoice was once set as recurring invoice
                    if ($invoice && ! $invoice->is_recurring && DB::table('invoices')
                            ->where('recurring_invoice_id', '=', $activity->invoice_id)
                            ->first(['invoices.id'])) {
                        $invoice->is_recurring = 1;

                        // **Fix for enabling a recurring invoice to be set as non-recurring**
                        if ($this->option('fix') == 'true') {
                            DB::table('invoices')
                                ->where('id', $invoice->id)
                                ->update(['is_recurring' => 1]);
                        }
                    }
                }

                if ($activity->activity_type_id == ACTIVITY_TYPE_CREATE_INVOICE
                    || $activity->activity_type_id == ACTIVITY_TYPE_CREATE_QUOTE) {

                    // Get original invoice amount
                    $update = DB::table('activities')
                                ->where('invoice_id', '=', $activity->invoice_id)
                                ->where('activity_type_id', '=', ACTIVITY_TYPE_UPDATE_INVOICE)
                                ->orderBy('id')
                                ->first(['json_backup']);
                    if ($update) {
                        $backup = json_decode($update->json_backup);
                        $invoice->amount = floatval($backup->amount);
                    }

                    $noAdjustment = $activity->activity_type_id == ACTIVITY_TYPE_CREATE_INVOICE
                        && $activity->adjustment == 0
                        && $invoice->amount > 0;

                    // **Fix for ninja invoices which didn't have the invoice_type_id value set
                    if ($noAdjustment && $client->account_id == 20432) {
                        $this->logMessage('No adjustment for ninja invoice');
                        $foundProblem = true;
                        $clientFix += $invoice->amount;
                        $activityFix = $invoice->amount;
                    // **Fix for allowing converting a recurring invoice to a normal one without updating the balance**
                    } elseif ($noAdjustment && $invoice->invoice_type_id == INVOICE_TYPE_STANDARD && ! $invoice->is_recurring) {
                        $this->logMessage("No adjustment for new invoice:{$activity->invoice_id} amount:{$invoice->amount} invoiceTypeId:{$invoice->invoice_type_id} isRecurring:{$invoice->is_recurring}");
                        $foundProblem = true;
                        $clientFix += $invoice->amount;
                        $activityFix = $invoice->amount;
                    // **Fix for updating balance when creating a quote or recurring invoice**
                    } elseif ($activity->adjustment != 0 && ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE || $invoice->is_recurring)) {
                        $this->logMessage("Incorrect adjustment for new invoice:{$activity->invoice_id} adjustment:{$activity->adjustment} invoiceTypeId:{$invoice->invoice_type_id} isRecurring:{$invoice->is_recurring}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_DELETE_INVOICE) {
                    // **Fix for updating balance when deleting a recurring invoice**
                    if ($activity->adjustment != 0 && $invoice->is_recurring) {
                        $this->logMessage("Incorrect adjustment for deleted invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        if ($activity->balance != $lastBalance) {
                            $clientFix -= $activity->adjustment;
                        }
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_ARCHIVE_INVOICE) {
                    // **Fix for updating balance when archiving an invoice**
                    if ($activity->adjustment != 0 && ! $invoice->is_recurring) {
                        $this->logMessage("Incorrect adjustment for archiving invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $activityFix = 0;
                        $clientFix += $activity->adjustment;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_UPDATE_INVOICE) {
                    // **Fix for updating balance when updating recurring invoice**
                    if ($activity->adjustment != 0 && $invoice->is_recurring) {
                        $this->logMessage("Incorrect adjustment for updated recurring invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    } elseif ((strtotime($activity->created_at) - strtotime($lastCreatedAt) <= 1) && $activity->adjustment > 0 && $activity->adjustment == $lastAdjustment) {
                        $this->logMessage("Duplicate adjustment for updated invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_UPDATE_QUOTE) {
                    // **Fix for updating balance when updating a quote**
                    if ($activity->balance != $lastBalance) {
                        $this->logMessage("Incorrect adjustment for updated quote adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix += $lastBalance - $activity->balance;
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_DELETE_PAYMENT) {
                    // **Fix for deleting payment after deleting invoice**
                    if ($activity->adjustment != 0 && $invoice->is_deleted && $activity->created_at > $invoice->deleted_at) {
                        $this->logMessage("Incorrect adjustment for deleted payment adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $activityFix = 0;
                        $clientFix -= $activity->adjustment;
                    }
                }

                if ($activityFix !== false || $clientFix !== false) {
                    $data = [
                        'balance' => $activity->balance + $clientFix,
                    ];

                    if ($activityFix !== false) {
                        $data['adjustment'] = $activityFix;
                    }

                    if ($this->option('fix') == 'true') {
                        DB::table('activities')
                            ->where('id', $activity->id)
                            ->update($data);
                    }
                }

                $lastBalance = $activity->balance;
                $lastAdjustment = $activity->adjustment;
                $lastCreatedAt = $activity->created_at;
            }

            if ($activity->balance + $clientFix != $client->actual_balance) {
                $this->logMessage("** Creating 'recovered update' activity **");
                if ($this->option('fix') == 'true') {
                    DB::table('activities')->insert([
                            'created_at' => new Carbon(),
                            'updated_at' => new Carbon(),
                            'account_id' => $client->account_id,
                            'client_id' => $client->id,
                            'adjustment' => $client->actual_balance - $activity->balance,
                            'balance' => $client->actual_balance,
                    ]);
                }
            }

            $data = ['balance' => $client->actual_balance];
            $this->logMessage("Corrected balance:{$client->actual_balance}");
            if ($this->option('fix') == 'true') {
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update($data);
            }
        }
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
            ['client_id', null, InputOption::VALUE_OPTIONAL, 'Client id', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }
}

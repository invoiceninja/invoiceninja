<?php namespace App\Console\Commands;

use DB;
use Mail;
use Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

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
 * Class CheckData
 */
class CheckData extends Command {

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

        if (!$this->option('client_id')) {
            $this->checkPaidToDate();
            $this->checkBlankInvoiceHistory();
        }

        $this->checkBalances();

        if (!$this->option('client_id')) {
            $this->checkAccountData();
        }

        $this->logMessage('Done');
        $errorEmail = env('ERROR_EMAIL');

        if ( ! $this->isValid) {
            if ($errorEmail) {
                Mail::raw($this->log, function ($message) use ($errorEmail) {
                    $message->to($errorEmail)
                            ->from(CONTACT_EMAIL)
                            ->subject('Check-Data');
                });
            } else {
                $this->info($this->log);
            }
        }
    }

    private function logMessage($str)
    {
        $this->log .= $str . "\n";
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

    private function checkAccountData()
    {
        $tables = [
            'activities' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_CONTACT,
                ENTITY_PAYMENT,
                ENTITY_INVITATION,
                ENTITY_USER
            ],
            'invoices' => [
                ENTITY_CLIENT,
                ENTITY_USER
            ],
            'payments' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_USER,
                ENTITY_INVITATION,
                ENTITY_CONTACT
            ],
            'tasks' => [
                ENTITY_INVOICE,
                ENTITY_CLIENT,
                ENTITY_USER
            ],
            'credits' => [
                ENTITY_CLIENT,
                ENTITY_USER
            ],
            'expenses' => [
                ENTITY_CLIENT,
                ENTITY_VENDOR,
                ENTITY_INVOICE,
                ENTITY_USER
            ]
        ];

        foreach ($tables as $table => $entityTypes) {
            foreach ($entityTypes as $entityType) {
                $records = DB::table($table)
                                ->join("{$entityType}s", "{$entityType}s.id", '=', "{$table}.{$entityType}_id");

                if ($entityType != ENTITY_CLIENT) {
                    $records = $records->join('clients', 'clients.id', '=', "{$table}.client_id");
                }

                $records = $records->where("{$table}.account_id", '!=', DB::raw("{$entityType}s.account_id"))
                                ->get(["{$table}.id", 'clients.account_id', 'clients.user_id']);

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

        $clients = $clients->groupBy('clients.id', 'clients.balance', 'clients.created_at')
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
                    if ($invoice && !$invoice->is_recurring && DB::table('invoices')
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
                        $this->logMessage("No adjustment for ninja invoice");
                        $foundProblem = true;
                        $clientFix += $invoice->amount;
                        $activityFix = $invoice->amount;
                    // **Fix for allowing converting a recurring invoice to a normal one without updating the balance**
                    } elseif ($noAdjustment && $invoice->invoice_type_id == INVOICE_TYPE_STANDARD && !$invoice->is_recurring) {
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
                    if ($activity->adjustment != 0 && !$invoice->is_recurring) {
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
                    } else if ((strtotime($activity->created_at) - strtotime($lastCreatedAt) <= 1) && $activity->adjustment > 0 && $activity->adjustment == $lastAdjustment) {
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
                } else if ($activity->activity_type_id == ACTIVITY_TYPE_DELETE_PAYMENT) {
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
                        'balance' => $activity->balance + $clientFix
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
                            'created_at' => new Carbon,
                            'updated_at' => new Carbon,
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
        ];
    }

}

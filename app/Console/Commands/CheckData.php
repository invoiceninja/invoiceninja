<?php namespace App\Console\Commands;

use DB;
use DateTime;
use Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

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


class CheckData extends Command {

    protected $name = 'ninja:check-data';
    protected $description = 'Check/fix data';
    
    public function fire()
    {
        $this->info(date('Y-m-d') . ' Running CheckData...');
        $today = new DateTime();

        if (!$this->option('client_id')) {
            // update client paid_to_date value
            $clients = DB::table('clients')
                        ->join('payments', 'payments.client_id', '=', 'clients.id')
                        ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                        ->where('payments.is_deleted', '=', 0)
                        ->where('invoices.is_deleted', '=', 0)
                        ->groupBy('clients.id')
                        ->havingRaw('clients.paid_to_date != sum(payments.amount) and clients.paid_to_date != 999999999.9999')
                        ->get(['clients.id', 'clients.paid_to_date', DB::raw('sum(payments.amount) as amount')]);
            $this->info(count($clients) . ' clients with incorrect paid to date');
            
            if ($this->option('fix') == 'true') {
                foreach ($clients as $client) {
                    DB::table('clients')
                        ->where('id', $client->id)
                        ->update(['paid_to_date' => $client->amount]);
                }
            }
        }

        // find all clients where the balance doesn't equal the sum of the outstanding invoices
        $clients = DB::table('clients')
                    ->join('invoices', 'invoices.client_id', '=', 'clients.id')
                    ->join('accounts', 'accounts.id', '=', 'clients.account_id');

        if ($this->option('client_id')) {
            $clients->where('clients.id', '=', $this->option('client_id'));
        } else {
            $clients->where('invoices.is_deleted', '=', 0)
                    ->where('invoices.is_quote', '=', 0)
                    ->where('invoices.is_recurring', '=', 0)
                    ->havingRaw('abs(clients.balance - sum(invoices.balance)) > .01 and clients.balance != 999999999.9999');
        }
                    
        $clients = $clients->groupBy('clients.id', 'clients.balance', 'clients.created_at')
                ->orderBy('clients.id', 'DESC')
                ->get(['clients.account_id', 'clients.id', 'clients.balance', 'clients.paid_to_date', DB::raw('sum(invoices.balance) actual_balance')]);
        $this->info(count($clients) . ' clients with incorrect balance/activities');

        foreach ($clients as $client) {
            $this->info("=== Client:{$client->id} Balance:{$client->balance} Actual Balance:{$client->actual_balance} ===");
            $foundProblem = false;
            $lastBalance = 0;
            $lastAdjustment = 0;
            $lastCreatedAt = null;
            $clientFix = false;
            $activities = DB::table('activities')
                        ->where('client_id', '=', $client->id)
                        ->orderBy('activities.id')
                        ->get(['activities.id', 'activities.created_at', 'activities.activity_type_id', 'activities.message', 'activities.adjustment', 'activities.balance', 'activities.invoice_id']);
            //$this->info(var_dump($activities));

            foreach ($activities as $activity) {

                $activityFix = false;

                if ($activity->invoice_id) {
                    $invoice = DB::table('invoices')
                                ->where('id', '=', $activity->invoice_id)
                                ->first(['invoices.amount', 'invoices.is_recurring', 'invoices.is_quote', 'invoices.deleted_at', 'invoices.id', 'invoices.is_deleted']);

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

                    // **Fix for allowing converting a recurring invoice to a normal one without updating the balance**
                    if ($noAdjustment && !$invoice->is_quote && !$invoice->is_recurring) {
                        $this->info("No adjustment for new invoice:{$activity->invoice_id} amount:{$invoice->amount} isQuote:{$invoice->is_quote} isRecurring:{$invoice->is_recurring}");
                        $foundProblem = true;
                        $clientFix += $invoice->amount;
                        $activityFix = $invoice->amount;
                    // **Fix for updating balance when creating a quote or recurring invoice**
                    } elseif ($activity->adjustment != 0 && ($invoice->is_quote || $invoice->is_recurring)) {
                        $this->info("Incorrect adjustment for new invoice:{$activity->invoice_id} adjustment:{$activity->adjustment} isQuote:{$invoice->is_quote} isRecurring:{$invoice->is_recurring}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_DELETE_INVOICE) {
                    // **Fix for updating balance when deleting a recurring invoice**
                    if ($activity->adjustment != 0 && $invoice->is_recurring) {
                        $this->info("Incorrect adjustment for deleted invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        if ($activity->balance != $lastBalance) {
                            $clientFix -= $activity->adjustment;
                        }
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_ARCHIVE_INVOICE) {
                    // **Fix for updating balance when archiving an invoice**
                    if ($activity->adjustment != 0 && !$invoice->is_recurring) {
                        $this->info("Incorrect adjustment for archiving invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $activityFix = 0;
                        $clientFix += $activity->adjustment;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_UPDATE_INVOICE) {
                    // **Fix for updating balance when updating recurring invoice**
                    if ($activity->adjustment != 0 && $invoice->is_recurring) {
                        $this->info("Incorrect adjustment for updated recurring invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    } else if ((strtotime($activity->created_at) - strtotime($lastCreatedAt) <= 1) && $activity->adjustment > 0 && $activity->adjustment == $lastAdjustment) {
                        $this->info("Duplicate adjustment for updated invoice adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix -= $activity->adjustment;
                        $activityFix = 0;
                    }
                } elseif ($activity->activity_type_id == ACTIVITY_TYPE_UPDATE_QUOTE) {
                    // **Fix for updating balance when updating a quote**
                    if ($activity->balance != $lastBalance) {
                        $this->info("Incorrect adjustment for updated quote adjustment:{$activity->adjustment}");
                        $foundProblem = true;
                        $clientFix += $lastBalance - $activity->balance;
                        $activityFix = 0;
                    }
                } else if ($activity->activity_type_id == ACTIVITY_TYPE_DELETE_PAYMENT) {
                    // **Fix for deleting payment after deleting invoice**
                    if ($activity->adjustment != 0 && $invoice->is_deleted && $activity->created_at > $invoice->deleted_at) {
                        $this->info("Incorrect adjustment for deleted payment adjustment:{$activity->adjustment}");
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
                $this->info("** Creating 'recovered update' activity **");
                if ($this->option('fix') == 'true') {
                    DB::table('activities')->insert([
                            'created_at' => new Carbon,
                            'updated_at' => new Carbon,
                            'account_id' => $client->account_id,
                            'client_id' => $client->id,
                            'message' => 'Recovered update to invoice [<a href="https://github.com/hillelcoren/invoice-ninja/releases/tag/v1.7.1" target="_blank">details</a>]',
                            'adjustment' => $client->actual_balance - $activity->balance,
                            'balance' => $client->actual_balance,
                    ]);
                }
            }

            $data = ['balance' => $client->actual_balance];
            $this->info("Corrected balance:{$client->actual_balance}");
            if ($this->option('fix') == 'true') {
                DB::table('clients')
                    ->where('id', $client->id)
                    ->update($data);
            }
        }

        $this->info('Done');
    }

    protected function getArguments()
    {
        return array(
            //array('example', InputArgument::REQUIRED, 'An example argument.'),
        );
    }

    protected function getOptions()
    {
        return array(
            array('fix', null, InputOption::VALUE_OPTIONAL, 'Fix data', null),
            array('client_id', null, InputOption::VALUE_OPTIONAL, 'Client id', null),
        );
    }

}
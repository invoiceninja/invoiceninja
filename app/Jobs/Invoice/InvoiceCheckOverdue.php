<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Invoice;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Mail\NinjaMailer;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesDates;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Queue\SerializesModels;
use App\Mail\Admin\InvoiceOverdueObject;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\Admin\InvoiceOverdueSummaryObject;
use App\Utils\Traits\Notifications\UserNotifies;

class InvoiceCheckOverdue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UserNotifies;
    use MakesDates;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('ninja.db.multi_db_enabled')) {
            $this->processOverdueInvoices();
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);
                $this->processOverdueInvoices();
            }
        }
    }

    /**
     * Process overdue invoices for the current database connection.
     * We check each company's timezone to ensure the invoice is truly overdue
     * based on the company's local time.
     */
    private function processOverdueInvoices(): void
    {
        // Get all companies that are not disabled
        Company::query()
            ->where('is_disabled', false)
            ->when(Ninja::isHosted(), function ($query) {
                $query->whereHas('account', function ($q) {
                    $q->where('is_flagged', false)
                      ->whereIn('plan', ['enterprise', 'pro'])
                      ->where('plan_expires', '>', now()->subHours(12));
                });
            })
            ->cursor()
            ->each(function (Company $company) {
                $this->checkCompanyOverdueInvoices($company);
            });
    }

    /**
     * Check for overdue invoices for a specific company,
     * using the company's timezone to determine if the invoice is overdue.
     *
     * Two scenarios trigger an overdue notification:
     * 1. partial > 0 && partial_due_date was yesterday (partial payment is overdue)
     * 2. partial == 0 && balance > 0 && due_date was yesterday (full invoice is overdue)
     *
     * To prevent duplicate notifications when running hourly, we only process
     * a company when it's currently between midnight and 1am in their timezone.
     * This ensures each company is only checked once per day.
     */
    private function checkCompanyOverdueInvoices(Company $company): void
    {
        // Get the company's timezone
        $timezone = $company->timezone();
        $timezone_name = $timezone ? $timezone->name : 'UTC';

        // Get the current hour in the company's timezone
        $now_in_company_tz = Carbon::now($timezone_name);

        // Only process this company if it's currently between midnight and 1am in their timezone
        // This prevents duplicate notifications when running hourly across all timezones
        if ($now_in_company_tz->hour !== 0) {
            return;
        }

        // Yesterday's date in the company's timezone (Y-m-d format)
        $yesterday = $now_in_company_tz->copy()->subDay()->format('Y-m-d');

        $overdue_invoices = Invoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->where('balance', '>', 0)
            ->whereHas('client', function ($query) {
                $query->where('is_deleted', 0)
                       ->whereNull('deleted_at');
            })
            // Check for overdue conditions based on partial or full invoice
            ->where(function ($query) use ($yesterday) {
                // Case 1: Partial payment is overdue (partial > 0 and partial_due_date was yesterday)
                $query->where(function ($q) use ($yesterday) {
                    $q->where('partial', '>', 0)
                      ->where('partial_due_date', $yesterday);
                })
                // Case 2: Full invoice is overdue (partial == 0 and due_date was yesterday)
                ->orWhere(function ($q) use ($yesterday) {
                    $q->where(function ($subq) {
                        $subq->where('partial', '=', 0)
                             ->orWhereNull('partial');
                    })
                      ->where('due_date', $yesterday);
                });
            })
            ->cursor()
            ->map(function ($invoice){

                return [
                    'id' => $invoice->id,
                    'client' => $invoice->client->present()->name(),
                    'number' => $invoice->number,
                    'amount' => max($invoice->partial, $invoice->balance),
                    'due_date' => $invoice->due_date,
                    'formatted_amount' => Number::formatMoney($invoice->balance, $invoice->client),
                    'formatted_due_date' => $this->translateDate($invoice->due_date, $invoice->company->date_format(), $invoice->company->locale()),
                ];

            })
            ->toArray();

            $this->sendOverdueNotifications($overdue_invoices, $company);
            
            // ->each(function ($invoice) {
            //     $this->notifyOverdueInvoice($invoice);
            // });
    }

    private function sendOverdueNotifications(array $overdue_invoices, Company $company): void
    {
        
        if(empty($overdue_invoices)){
            return;
        }

        $nmo = new NinjaMailerObject();
        $nmo->company = $company;
        $nmo->settings = $company->settings;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            if (! $user) {
                continue;
            }

            $overdue_invoices_collection = $overdue_invoices;

            $invoice = Invoice::withTrashed()->find($overdue_invoices[0]['id']);

            $table_headers = [
                'client' => ctrans('texts.client'),
                'number' => ctrans('texts.invoice_number'),
                'formatted_due_date' => ctrans('texts.due_date'),
                'formatted_amount' => ctrans('texts.amount'),
            ];

            /** filter down the set if the user only has notifications for their own invoices */
            if(isset($company_user->notifications->email) && is_array($company_user->notifications->email) && in_array('invoice_late_user', $company_user->notifications->email)){

                $overdue_invoices_collection = collect($overdue_invoices)
                            ->filter(function ($overdue_invoice) use ($user) {
                                $invoice = Invoice::withTrashed()->find($overdue_invoice['id']);
                                // nlog([$invoice->user_id, $user->id, $invoice->assigned_user_id, $user->id]);
                                return $invoice->user_id == $user->id || $invoice->assigned_user_id == $user->id;
                        })
                        ->toArray();

                if(count($overdue_invoices_collection) === 0){
                    continue;
                }

                $invoice = Invoice::withTrashed()->find(end($overdue_invoices_collection)['id']);

            }

            $nmo->mailable = new NinjaMailer((new InvoiceOverdueSummaryObject($overdue_invoices_collection, $table_headers, $company, $company_user->portalType()))->build());
                
            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes(
                $invoice->invitations()->first(),
                $company_user,
                'invoice',
                ['all_notifications', 'invoice_late', 'invoice_late_all', 'invoice_late_user']
            );


            /* If one of the methods is email then we fire the mailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);
            }
        }

    }

    /**
     * Send notifications for an overdue invoice to all relevant company users.
     * @deprecated in favour of sendOverdueNotifications to send a summary email to all users
     */
    /** @phpstan-ignore-next-line */
    private function notifyOverdueInvoice(Invoice $invoice): void
    {
        $nmo = new NinjaMailerObject();
        $nmo->company = $invoice->company;
        $nmo->settings = $invoice->company->settings;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($invoice->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            if (! $user) {
                continue;
            }

            $nmo->mailable = new NinjaMailer((new InvoiceOverdueObject($invoice, $invoice->company, $company_user->portalType()))->build());

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes(
                $invoice->invitations()->first(),
                $company_user,
                'invoice',
                ['all_notifications', 'invoice_late', 'invoice_late_all', 'invoice_late_user']
            );

            /* If one of the methods is email then we fire the mailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);
            }
        }
    }
}


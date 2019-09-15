<?php

namespace App\Console\Commands;

use App\Libraries\CurlUtils;
use Carbon;
use Str;
use Cache;
use Utils;
use Exception;
use DateTime;
use Auth;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Models\Currency;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Models\ScheduledReport;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App\Jobs\ExportReportResults;
use App\Jobs\RunReport;

/**
 * Class SendReminders.
 */
class SendReminders extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-reminders';

    /**
     * @var string
     */
    protected $description = 'Send reminder emails';

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var accountRepository
     */
    protected $accountRepo;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * SendReminders constructor.
     *
     * @param Mailer            $mailer
     * @param InvoiceRepository $invoiceRepo
     * @param accountRepository $accountRepo
     */
    public function __construct(InvoiceRepository $invoiceRepo, PaymentService $paymentService, AccountRepository $accountRepo, UserMailer $userMailer)
    {
        parent::__construct();

        $this->paymentService = $paymentService;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
    }

    public function handle()
    {
        $this->info(date('r') . ' Running SendReminders...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        $this->billInvoices();
        $this->chargeLateFees();
        $this->sendReminderEmails();
        $this->sendScheduledReports();
        $this->loadExchangeRates();

        $this->info(date('r') . ' Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject("SendReminders [{$database}]: Finished successfully");
            });
        }
    }

    private function billInvoices()
    {
        $today = new DateTime();

        $delayedAutoBillInvoices = Invoice::with('account.timezone', 'recurring_invoice', 'invoice_items', 'client', 'user')
            ->whereRaw('is_deleted IS FALSE AND deleted_at IS NULL AND is_recurring IS FALSE AND is_public IS TRUE
            AND balance > 0 AND due_date = ? AND recurring_invoice_id IS NOT NULL',
                [$today->format('Y-m-d')])
            ->orderBy('invoices.id', 'asc')
            ->get();
        $this->info(date('r ') . $delayedAutoBillInvoices->count() . ' due recurring invoice instance(s) found');

        /** @var Invoice $invoice */
        foreach ($delayedAutoBillInvoices as $invoice) {
            if ($invoice->isPaid()) {
                continue;
            }

            if ($invoice->getAutoBillEnabled() && $invoice->client->autoBillLater()) {
                $this->info(date('r') . ' Processing Autobill-delayed Invoice: ' . $invoice->id);
                Auth::loginUsingId($invoice->activeUser()->id);
                $this->paymentService->autoBillInvoice($invoice);
                Auth::logout();
            }
        }
    }

    private function chargeLateFees()
    {
        $accounts = $this->accountRepo->findWithFees();
        $this->info(date('r ') . $accounts->count() . ' accounts found with fees enabled');

        foreach ($accounts as $account) {
            if (! $account->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($account, false);
            $this->info(date('r ') . $account->name . ': ' . $invoices->count() . ' invoices found');

            foreach ($invoices as $invoice) {
                if ($reminder = $account->getInvoiceReminder($invoice, false)) {
                    $this->info(date('r') . ' Charge fee: ' . $invoice->id);
                    $account->loadLocalizationSettings($invoice->client); // support trans to add fee line item
                    $number = preg_replace('/[^0-9]/', '', $reminder);

                    $amount = $account->account_email_settings->{"late_fee{$number}_amount"};
                    $percent = $account->account_email_settings->{"late_fee{$number}_percent"};
                    $this->invoiceRepo->setLateFee($invoice, $amount, $percent);
                }
            }
        }
    }

    private function sendReminderEmails()
    {
        $accounts = $this->accountRepo->findWithReminders();
        $this->info(date('r ') . count($accounts) . ' accounts found with reminders enabled');

        foreach ($accounts as $account) {
            if (! $account->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            // standard reminders
            $invoices = $this->invoiceRepo->findNeedingReminding($account);
            $this->info(date('r ') . $account->name . ': ' . $invoices->count() . ' invoices found');

            foreach ($invoices as $invoice) {
                if ($reminder = $account->getInvoiceReminder($invoice)) {
                    if ($invoice->last_sent_date == date('Y-m-d')) {
                        continue;
                    }
                    $this->info(date('r') . ' Send email: ' . $invoice->id);
                    dispatch(new SendInvoiceEmail($invoice, $invoice->user_id, $reminder));
                }
            }

            // endless reminders
            $invoices = $this->invoiceRepo->findNeedingEndlessReminding($account);
            $this->info(date('r ') . $account->name . ': ' . $invoices->count() . ' endless invoices found');

            foreach ($invoices as $invoice) {
                if ($invoice->last_sent_date == date('Y-m-d')) {
                    continue;
                }
                $this->info(date('r') . ' Send email: ' . $invoice->id);
                dispatch(new SendInvoiceEmail($invoice, $invoice->user_id, 'reminder4'));
            }
        }
    }

    private function sendScheduledReports()
    {
        $scheduledReports = ScheduledReport::where('send_date', '<=', date('Y-m-d'))
            ->with('user', 'account.company')
            ->get();
        $this->info(date('r ') . $scheduledReports->count() . ' scheduled reports');

        foreach ($scheduledReports as $scheduledReport) {
            $this->info(date('r') . ' Processing report: ' . $scheduledReport->id);

            $user = $scheduledReport->user;
            $account = $scheduledReport->account;
            $account->loadLocalizationSettings();

            if (! $account->hasFeature(FEATURE_REPORTS)) {
                continue;
            }

            $config = (array) json_decode($scheduledReport->config);
            $reportType = $config['report_type'];

            // send email as user
            auth()->onceUsingId($user->id);

            $report = dispatch_now(new RunReport($scheduledReport->user, $reportType, $config, true));
            $file = dispatch_now(new ExportReportResults($scheduledReport->user, $config['export_format'], $reportType, $report->exportParams));

            if ($file) {
                try {
                    $this->userMailer->sendScheduledReport($scheduledReport, $file);
                    $this->info(date('r') . ' Sent report');
                } catch (Exception $exception) {
                    $this->info(date('r') . ' ERROR: ' . $exception->getMessage());
                }
            } else {
                $this->info(date('r') . ' ERROR: Failed to run report');
            }

            $scheduledReport->updateSendDate();

            auth()->logout();
        }
    }

    private function loadExchangeRates()
    {
        if (Utils::isNinjaDev()) {
            return;
        }

        if (config('ninja.exchange_rates_enabled')) {
            $this->info(date('r') . ' Loading latest exchange rates...');

            $response = CurlUtils::get(config('ninja.exchange_rates_url'));
            $data = json_decode($response);

            if ($data && property_exists($data, 'rates')) {
                Currency::whereCode(config('ninja.exchange_rates_base'))->update(['exchange_rate' => 1]);

                foreach ($data->rates as $code => $rate) {
                    Currency::whereCode($code)->update(['exchange_rate' => $rate]);
                }
            } else {
                $this->info(date('r') . ' Error: failed to load exchange rates - ' . $response);
                \DB::table('currencies')->update(['exchange_rate' => 1]);
            }
        } else {
            \DB::table('currencies')->update(['exchange_rate' => 1]);
        }

        CurlUtils::get(SITE_URL . '?clear_cache=true');
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
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }
}

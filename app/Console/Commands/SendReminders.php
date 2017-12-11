<?php

namespace App\Console\Commands;

use Carbon;
use Str;
use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Mailers\UserMailer;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Models\ScheduledReport;
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
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var accountRepository
     */
    protected $accountRepo;

    /**
     * SendReminders constructor.
     *
     * @param Mailer            $mailer
     * @param InvoiceRepository $invoiceRepo
     * @param accountRepository $accountRepo
     */
    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, AccountRepository $accountRepo, UserMailer $userMailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
    }

    public function fire()
    {
        $this->info(date('r') . ' Running SendReminders...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        $this->chargeLateFees();
        $this->setReminderEmails();
        $this->sendScheduledReports();

        $this->info('Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject("SendReminders [{$database}]: Finished successfully");
            });
        }
    }

    private function chargeLateFees()
    {
        $accounts = $this->accountRepo->findWithFees();
        $this->info(count($accounts) . ' accounts found with fees');

        foreach ($accounts as $account) {
            if (! $account->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($account, false);
            $this->info($account->name . ': ' . count($invoices) . ' invoices found');

            foreach ($invoices as $invoice) {
                if ($reminder = $account->getInvoiceReminder($invoice, false)) {
                    $this->info('Charge fee: ' . $invoice->id);
                    $account->loadLocalizationSettings($invoice->client); // support trans to add fee line item
                    $number = preg_replace('/[^0-9]/', '', $reminder);

                    $amount = $account->account_email_settings->{"late_fee{$number}_amount"};
                    $percent = $account->account_email_settings->{"late_fee{$number}_percent"};
                    $this->invoiceRepo->setLateFee($invoice, $amount, $percent);
                }
            }
        }
    }

    private function setReminderEmails()
    {
        $accounts = $this->accountRepo->findWithReminders();
        $this->info(count($accounts) . ' accounts found with reminders');

        foreach ($accounts as $account) {
            if (! $account->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($account);
            $this->info($account->name . ': ' . count($invoices) . ' invoices found');

            foreach ($invoices as $invoice) {
                if ($reminder = $account->getInvoiceReminder($invoice)) {
                    $this->info('Send email: ' . $invoice->id);
                    $this->mailer->sendInvoice($invoice, $reminder);
                }
            }
        }
    }

    private function sendScheduledReports()
    {
        $scheduledReports = ScheduledReport::where('send_date', '<=', date('Y-m-d'))
            ->with('user', 'account.company')
            ->get();
        $this->info(count($scheduledReports) . ' scheduled reports');

        foreach ($scheduledReports as $scheduledReport) {
            $user = $scheduledReport->user;
            $account = $scheduledReport->account;

            if (! $account->hasFeature(FEATURE_REPORTS)) {
                continue;
            }

            $config = (array) json_decode($scheduledReport->config);
            $reportType = $config['report_type'];

            $report = dispatch(new RunReport($scheduledReport->user, $reportType, $config, true));
            $file = dispatch(new ExportReportResults($scheduledReport->user, $config['export_format'], $reportType, $report->exportParams));

            if ($file) {
                $this->userMailer->sendScheduledReport($scheduledReport, $file);
            }

            $scheduledReport->updateSendDate();
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
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }
}

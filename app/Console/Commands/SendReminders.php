<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Repositories\InvoiceRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

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
    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, AccountRepository $accountRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->accountRepo = $accountRepo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d') . ' Running SendReminders...');

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        $accounts = $this->accountRepo->findWithReminders();
        $this->info(count($accounts) . ' accounts found');

        /** @var \App\Models\Account $account */
        foreach ($accounts as $account) {
            if (! $account->hasFeature(FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                continue;
            }

            $invoices = $this->invoiceRepo->findNeedingReminding($account);
            $this->info($account->name . ': ' . count($invoices) . ' invoices found');

            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                if ($reminder = $account->getInvoiceReminder($invoice)) {
                    $this->info('Send to ' . $invoice->id);
                    $this->mailer->sendInvoice($invoice, $reminder);
                }
            }
        }

        $this->info('Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail, $database) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject("SendReminders [{$database}]: Finished successfully");
            });
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

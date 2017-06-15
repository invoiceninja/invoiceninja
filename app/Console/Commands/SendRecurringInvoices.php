<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\PaymentService;
use DateTime;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Auth;

/**
 * Class SendRecurringInvoices.
 */
class SendRecurringInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-invoices';

    /**
     * @var string
     */
    protected $description = 'Send recurring invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * SendRecurringInvoices constructor.
     *
     * @param Mailer            $mailer
     * @param InvoiceRepository $invoiceRepo
     * @param PaymentService    $paymentService
     */
    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d H:i:s') . ' Running SendRecurringInvoices...');
        $today = new DateTime();

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        // check for counter resets
        $accounts = Account::where('reset_counter_frequency_id', '>', 0)
            ->orderBy('id', 'asc')
            ->get();

        foreach ($accounts as $account) {
            $account->checkCounterReset();
        }

        $invoices = Invoice::with('account.timezone', 'invoice_items', 'client', 'user')
            ->whereRaw('is_deleted IS FALSE AND deleted_at IS NULL AND is_recurring IS TRUE AND is_public IS TRUE AND frequency_id > 0 AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)', [$today, $today])
            ->orderBy('id', 'asc')
            ->get();
        $this->info(count($invoices).' recurring invoice(s) found');

        foreach ($invoices as $recurInvoice) {
            $shouldSendToday = $recurInvoice->shouldSendToday();

            if (! $shouldSendToday) {
                continue;
            }

            $this->info('Processing Invoice: '. $recurInvoice->id);

            $account = $recurInvoice->account;
            $account->loadLocalizationSettings($recurInvoice->client);
            Auth::loginUsingId($recurInvoice->user_id);
            $invoice = $this->invoiceRepo->createRecurringInvoice($recurInvoice);

            if ($invoice && ! $invoice->isPaid()) {
                $this->info('Sending Invoice');
                $this->mailer->sendInvoice($invoice);
            }
            Auth::logout();
        }

        $delayedAutoBillInvoices = Invoice::with('account.timezone', 'recurring_invoice', 'invoice_items', 'client', 'user')
            ->whereRaw('is_deleted IS FALSE AND deleted_at IS NULL AND is_recurring IS FALSE AND is_public IS TRUE
            AND balance > 0 AND due_date = ? AND recurring_invoice_id IS NOT NULL',
                [$today->format('Y-m-d')])
            ->orderBy('invoices.id', 'asc')
            ->get();
        $this->info(count($delayedAutoBillInvoices).' due recurring invoice instance(s) found');

        /** @var Invoice $invoice */
        foreach ($delayedAutoBillInvoices as $invoice) {
            if ($invoice->isPaid()) {
                continue;
            }

            if ($invoice->getAutoBillEnabled() && $invoice->client->autoBillLater()) {
                $this->info('Processing Autobill-delayed Invoice: ' . $invoice->id);
                Auth::loginUsingId($invoice->user_id);
                $this->paymentService->autoBillInvoice($invoice);
                Auth::logout();
            }
        }

        $this->info(date('Y-m-d H:i:s') . ' Done');
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

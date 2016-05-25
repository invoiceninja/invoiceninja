<?php namespace App\Console\Commands;

use DateTime;
use Carbon;
use Utils;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\PaymentService;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Invitation;

class SendRecurringInvoices extends Command
{
    protected $name = 'ninja:send-invoices';
    protected $description = 'Send recurring invoices';
    protected $mailer;
    protected $invoiceRepo;
    protected $paymentService;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendRecurringInvoices...');
        $today = new DateTime();

        $invoices = Invoice::with('account.timezone', 'invoice_items', 'client', 'user')
            ->whereRaw('is_deleted IS FALSE AND deleted_at IS NULL AND is_recurring IS TRUE AND frequency_id > 0 AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)', array($today, $today))
            ->orderBy('id', 'asc')
            ->get();
        $this->info(count($invoices).' recurring invoice(s) found');

        foreach ($invoices as $recurInvoice) {
            if (!$recurInvoice->user->confirmed) {
                continue;
            }
            
            $recurInvoice->account->loadLocalizationSettings($recurInvoice->client);
            $this->info('Processing Invoice '.$recurInvoice->id.' - Should send '.($recurInvoice->shouldSendToday() ? 'YES' : 'NO'));
            $invoice = $this->invoiceRepo->createRecurringInvoice($recurInvoice);

            if ($invoice && !$invoice->isPaid()) {
                $this->info('Sending Invoice');
                $this->mailer->sendInvoice($invoice);
            }
        }

        $delayedAutoBillInvoices = Invoice::with('account.timezone', 'recurring_invoice', 'invoice_items', 'client', 'user')
            ->leftJoin('invoices as recurring_invoice', 'invoices.recurring_invoice_id', '=', 'recurring_invoice.id')
            ->whereRaw('invoices.is_deleted IS FALSE AND invoices.deleted_at IS NULL AND invoices.is_recurring IS FALSE 
            AND invoices.balance > 0 AND invoices.due_date = ?
            AND (recurring_invoice.auto_bill = ? OR (recurring_invoice.auto_bill != ? AND recurring_invoice.client_enable_auto_bill IS TRUE))',
                array($today->format('Y-m-d'), AUTO_BILL_ALWAYS, AUTO_BILL_OFF))
            ->orderBy('invoices.id', 'asc')
            ->get();
        $this->info(count($delayedAutoBillInvoices).' due recurring auto bill invoice instance(s) found');

        foreach ($delayedAutoBillInvoices as $invoice) {
            $autoBill = $invoice->getAutoBillEnabled();
            $billNow = false;

            if ($autoBill && !$invoice->isPaid()) {
                $billNow = $invoice->account->auto_bill_on_due_date;

                if (!$billNow) {
                    $paymentMethod = $this->invoiceService->getClientDefaultPaymentMethod($invoice->client);

                    if ($paymentMethod && $paymentMethod->requiresDelayedAutoBill()) {
                        $billNow = true;
                    }
                }
            }

            $this->info('Processing Invoice '.$invoice->id.' - Should bill '.($billNow ? 'YES' : 'NO'));

            if ($billNow) {
                $this->paymentService->autoBillInvoice($invoice);
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
            //array('example', null, InputOption::VALUE_OPTIONAL, 'An example option.', null),
        );
    }
}

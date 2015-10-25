<?php namespace App\Console\Commands;

use DateTime;
use Carbon;
use Utils;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Invitation;

class SendRecurringInvoices extends Command
{
    protected $name = 'ninja:send-invoices';
    protected $description = 'Send recurring invoices';
    protected $mailer;
    protected $invoiceRepo;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
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

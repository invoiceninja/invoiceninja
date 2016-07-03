<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Services\PaymentService;
use App\Models\Invoice;

/**
 * Class ChargeRenewalInvoices
 */
class ChargeRenewalInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:charge-renewals';

    /**
     * @var string
     */
    protected $description = 'Charge renewal invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * ChargeRenewalInvoices constructor.
     * @param Mailer $mailer
     * @param AccountRepository $repo
     * @param PaymentService $paymentService
     */
    public function __construct(Mailer $mailer, AccountRepository $repo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->accountRepo = $repo;
        $this->paymentService = $paymentService;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' ChargeRenewalInvoices...');

        $account = $this->accountRepo->getNinjaAccount();
        $invoices = Invoice::whereAccountId($account->id)
                        ->whereDueDate(date('Y-m-d'))
                        ->with('client')
                        ->orderBy('id')
                        ->get();

        $this->info(count($invoices).' invoices found');

        foreach ($invoices as $invoice) {
            $this->info("Charging invoice {$invoice->invoice_number}");
            $this->paymentService->autoBillInvoice($invoice);
        }

        $this->info('Done');
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
        return [];
    }
}

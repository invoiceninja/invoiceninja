<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Services\PaymentService;
use App\Models\Invoice;
use App\Models\Account;
use Exception;

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

        $ninjaAccount = $this->accountRepo->getNinjaAccount();
        $invoices = Invoice::whereAccountId($ninjaAccount->id)
                        ->whereDueDate(date('Y-m-d'))
                        ->where('balance', '>', 0)
                        ->with('client')
                        ->orderBy('id')
                        ->get();

        $this->info(count($invoices).' invoices found');

        foreach ($invoices as $invoice) {

            // check if account has switched to free since the invoice was created
            $account = Account::find($invoice->client->public_id);

            if ( ! $account) {
                continue;
            }

            $company = $account->company;
            if ( ! $company->plan || $company->plan == PLAN_FREE) {
                continue;
            }

            try {
                $this->info("Charging invoice {$invoice->invoice_number}");
                $this->paymentService->autoBillInvoice($invoice);
            } catch (Exception $exception) {
                $this->info('Error: ' . $exception->getMessage());
            }
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

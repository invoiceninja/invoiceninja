<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;
use App\Services\PaymentService;
use Illuminate\Console\Command;
use Carbon;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ChargeRenewalInvoices.
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
     *
     * @param Mailer            $mailer
     * @param AccountRepository $repo
     * @param PaymentService    $paymentService
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

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

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

            if (! $account) {
                continue;
            }

            $company = $account->company;
            if (! $company->plan || $company->plan == PLAN_FREE) {
                continue;
            }

            if (Carbon::parse($company->plan_expires)->isFuture()) {
                $this->info('Skipping invoice ' . $invoice->invoice_number . ' [plan not expired]');
                continue;
            }

            $this->info("Charging invoice {$invoice->invoice_number}");
            if (! $this->paymentService->autoBillInvoice($invoice)) {
                $this->info('Failed to auto-bill, emailing invoice');
                $this->mailer->sendInvoice($invoice);
            }
        }

        $this->info('Done');

        if ($errorEmail = env('ERROR_EMAIL')) {
            \Mail::raw('EOM', function ($message) use ($errorEmail) {
                $message->to($errorEmail)
                        ->from(CONTACT_EMAIL)
                        ->subject('ChargeRenewalInvoices: Finished successfully');
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

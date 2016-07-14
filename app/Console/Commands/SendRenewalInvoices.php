<?php namespace App\Console\Commands;

use Utils;
use Illuminate\Console\Command;
use App\Models\Company;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;

/**
 * Class SendRenewalInvoices
 */
class SendRenewalInvoices extends Command
{
    /**
     * @var string
     */
    protected $name = 'ninja:send-renewals';

    /**
     * @var string
     */
    protected $description = 'Send renewal invoices';

    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * SendRenewalInvoices constructor.
     *
     * @param Mailer $mailer
     * @param AccountRepository $repo
     */
    public function __construct(Mailer $mailer, AccountRepository $repo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->accountRepo = $repo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendRenewalInvoices...');

        // get all accounts with plans expiring in 10 days
        $companies = Company::whereRaw('datediff(plan_expires, curdate()) = 10')
                        ->orderBy('id')
                        ->get();
        $this->info(count($companies).' companies found renewing in 10 days');

        foreach ($companies as $company) {
            if (!count($company->accounts)) {
                continue;
            }

            $account = $company->accounts->sortBy('id')->first();
            $plan = [];
            $plan['plan'] = $company->plan;
            $plan['term'] = $company->plan_term;
            $plan['num_users'] = $company->num_users;
            $plan['price'] = min($company->plan_price, Utils::getPlanPrice($plan));

            if ($company->pending_plan) {
                $plan['plan'] = $company->pending_plan;
                $plan['term'] = $company->pending_term;
                $plan['num_users'] = $company->pending_num_users;
                $plan['price'] = min($company->pending_plan_price, Utils::getPlanPrice($plan));
            }

            if ($plan['plan'] == PLAN_FREE || !$plan['plan'] || !$plan['term'] || !$plan['price']){
                continue;
            }

            $client = $this->accountRepo->getNinjaClient($account);
            $invitation = $this->accountRepo->createNinjaInvoice($client, $account, $plan, 0, false);

            // set the due date to 10 days from now
            $invoice = $invitation->invoice;
            $invoice->due_date = date('Y-m-d', strtotime('+ 10 days'));
            $invoice->save();

            $term = $plan['term'];
            $plan = $plan['plan'];

            if ($term == PLAN_TERM_YEARLY) {
                $this->mailer->sendInvoice($invoice);
                $this->info("Sent {$term}ly {$plan} invoice to {$client->getDisplayName()}");
            } else {
                $this->info("Created {$term}ly {$plan} invoice for {$client->getDisplayName()}");
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

<?php namespace App\Console\Commands;

use DB;
use DateTime;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Models\Company;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\AccountRepository;

class SendRenewalInvoices extends Command
{
    protected $name = 'ninja:send-renewals';
    protected $description = 'Send renewal invoices';
    protected $mailer;
    protected $accountRepo;

    public function __construct(Mailer $mailer, AccountRepository $repo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->accountRepo = $repo;
    }

    public function fire()
    {
        $this->info(date('Y-m-d').' Running SendRenewalInvoices...');
        $today = new DateTime();
        $sentTo = [];

        // get all accounts with plans expiring in 10 days
        $companies = Company::whereRaw('datediff(plan_expires, curdate()) = 10')
                        ->orderBy('id')
                        ->get();
        $this->info(count($companies).' companies found');

        foreach ($companies as $company) {
            if (!count($company->accounts)) {
                continue;
            }
            
            $account = $company->accounts->sortBy('id')->first();
            $plan = $company->plan;
            $term = $company->plan_term;
            
            if ($company->pending_plan) {
                $plan = $company->pending_plan;
                $term = $company->pending_term;
            }
            
            if ($plan == PLAN_FREE || !$plan || !$term ){
                continue;
            }
            
            $client = $this->accountRepo->getNinjaClient($account);
            $invitation = $this->accountRepo->createNinjaInvoice($client, $account, $plan, $term);

            // set the due date to 10 days from now
            $invoice = $invitation->invoice;
            $invoice->due_date = date('Y-m-d', strtotime('+ 10 days'));
            $invoice->save();

            if ($term == PLAN_TERM_YEARLY) {
                $this->mailer->sendInvoice($invoice);
                $this->info("Sent {$term}ly {$plan} invoice to {$client->getDisplayName()}");
            } else {
                $this->info("Created {$term}ly {$plan} invoice for {$client->getDisplayName()}");
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

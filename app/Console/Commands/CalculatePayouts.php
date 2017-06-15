<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DbServer;
use App\Models\User;
use App\Models\Company;

class CalculatePayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:calculate-payouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate referral payouts';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Running CalculatePayouts...');

        $servers = DbServer::orderBy('id')->get(['name']);
        $userMap = [];

        foreach ($servers as $server) {
            $this->info('Processing users: ' . $server->name);
            config(['database.default' => $server->name]);

            $users = User::where('referral_code', '!=', '')
                ->get(['email', 'referral_code']);
            foreach ($users as $user) {
                $userMap[$user->referral_code] = $user->email;
            }
        }

        foreach ($servers as $server) {
            $this->info('Processing companies: ' . $server->name);
            config(['database.default' => $server->name]);

            $companies = Company::where('referral_code', '!=', '')
                ->with('payment.client.payments')
                ->whereNotNull('payment_id')
                ->get();

            foreach ($companies as $company) {
                $user = $userMap[$company->referral_code];
                $payment = $company->payment;
                $client = $payment->client;

                $this->info("User: $user");

                foreach ($client->payments as $payment) {
                    $this->info("Date: $payment->payment_date, Amount: $payment->amount, Reference: $payment->transaction_reference");
                }
            }
        }
    }

    protected function getOptions()
    {
        return [

        ];
    }

}

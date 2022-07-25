<?php

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SubdomainFill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:subdomain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pad subdomains';

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
     * @return int
     */
    public function handle()
    {
        $c1 = Company::on('db-ninja-01')->whereNull('subdomain')->orWhere('subdomain', '')->get();
        $c2 = Company::on('db-ninja-02')->whereNull('subdomain')->orWhere('subdomain', '')->get();

        $c1->each(function ($company) {
            $company->subdomain = MultiDB::randomSubdomainGenerator();
            $company->save();
        });

        $c2->each(function ($company) {
            $company->subdomain = MultiDB::randomSubdomainGenerator();
            $company->save();
        });
    }
}

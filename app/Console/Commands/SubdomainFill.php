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


        $c1->each(function ($company){

            $company->subdomain = MultiDB::randomSubdomainGenerator();
            $company->save();

        });

        $c2->each(function ($company){

            $company->subdomain = MultiDB::randomSubdomainGenerator();
            $company->save();

        });


        // $db1 = Company::on('db-ninja-01')->get();

        // $db1->each(function ($company){

        //     $db2 = Company::on('db-ninja-02a')->find($company->id);

        //     if($db2)
        //     {
        //         $db2->subdomain = $company->subdomain;
        //         $db2->save();
        //     }
            
        // });


        // $db1 = null;
        // $db2 = null;

        // $db2 = Company::on('db-ninja-02')->get();

        // $db2->each(function ($company){

        //     $db1 = Company::on('db-ninja-01a')->find($company->id);

        //     if($db1)
        //     {
        //         $db1->subdomain = $company->subdomain;
        //         $db1->save();
        //     }
        // });
    }

}

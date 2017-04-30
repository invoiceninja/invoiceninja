<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use App\Models\DbServer;

class InitLookup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:init-lookup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize lookup tables';

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
        $this->info(date('Y-m-d') . ' Running InitLookup...');

        DB::purge(DB::getDefaultConnection());
        DB::Reconnect('db-ninja-0');

        if (! DbServer::count()) {
            DbServer::create(['name' => 'db-ninja-1']);
        }
    }
}

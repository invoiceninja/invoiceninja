<?php

namespace App\Console\Commands;

use App\Models\Design;
use Illuminate\Console\Command;

class DesignUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:design-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the system designs when changes are made.';

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
        foreach (Design::whereIsCustom(false)->get() as $design) {
            $class = 'App\Designs\\'.$design->name;
            $invoice_design = new $class();

            $design_object = new \stdClass;
            $design_object->includes = $invoice_design->includes() ?: '';
            $design_object->header = $invoice_design->header() ?: '';
            $design_object->body = $invoice_design->body() ?: '';
            $design_object->product = $invoice_design->product() ?: '';
            $design_object->task = $invoice_design->task() ?: '';
            $design_object->footer = $invoice_design->footer() ?: '';

            $design->design = $design_object;
            $design->save();
        }
    }
}

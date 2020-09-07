<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

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
            $class = 'App\Services\PdfMaker\Designs\\'.$design->name;
            $invoice_design = new $class();
            $invoice_design->document();

            $design_object = new \stdClass;
            $design_object->includes = $invoice_design->getSectionHTML('includes');
            $design_object->header = $invoice_design->getSectionHTML('head', false);
            $design_object->body = $invoice_design->getSectionHTML('body', false);
            $design_object->product = $invoice_design->getSectionHTML('product-table');
            $design_object->task = $invoice_design->getSectionHTML('task-table');
            $design_object->footer = $invoice_design->getSectionHTML('footer', false);

            $design->design = $design_object;
            $design->save();
        }
    }
}

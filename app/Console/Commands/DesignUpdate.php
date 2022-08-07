<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Design;
use Illuminate\Console\Command;
use stdClass;

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
        //always return state to first DB

        $current_db = config('database.default');

        if (! config('ninja.db.multi_db_enabled')) {
            $this->handleOnDb();
        } else {

            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->handleOnDb($db);
            }

            MultiDB::setDB($current_db);
        }
    }

    private function handleOnDb()
    {
        foreach (Design::whereIsCustom(false)->get() as $design) {
            $invoice_design = new \App\Services\PdfMaker\Design(strtolower($design->name));
            $invoice_design->document();

            $design_object = new stdClass;
            $design_object->includes = $invoice_design->getSectionHTML('style');
            $design_object->header = $invoice_design->getSectionHTML('header');
            $design_object->body = $invoice_design->getSectionHTML('body');
            $design_object->product = '';
            $design_object->task = '';
            $design_object->footer = $invoice_design->getSectionHTML('footer');

            $design->design = $design_object;
            $design->save();
        }
    }
}

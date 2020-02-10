<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use Codedge\Updater\UpdaterManager;
use Illuminate\Foundation\Bus\DispatchesJobs;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {

    }

    public function update(UpdaterManager $updater)
    {
    	
    	$updater->update();

    }
}

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

use App\Utils\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as Input;


/**
 * Class SetupController
 */
class SetupController extends Controller
{

	public function index()
	{

		$system_health = SystemHealth::check();

		return view();
		
	}

	public function doSetup()
	{

	}

}
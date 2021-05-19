<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Jobs\Util\ImportStripeCustomers;
use App\Jobs\Util\StripeUpdatePaymentMethods;

class StripeController extends BaseController
{

	public function update()
	{
		if(auth()->user()->isAdmin())
		{
			
			StripeUpdatePaymentMethods::dispatch(auth()->user()->company());

			return response()->json(['message' => 'Processing'], 200);

		}

		
		return response()->json(['message' => 'Unauthorized'], 403);
	}

	public function import()
	{

		if(auth()->user()->isAdmin())
		{
			
			ImportStripeCustomers::dispatch(auth()->user()->company());

			return response()->json(['message' => 'Processing'], 200);

		}

		
		return response()->json(['message' => 'Unauthorized'], 403);
	}

}
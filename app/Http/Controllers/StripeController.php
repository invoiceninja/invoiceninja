<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Jobs\Util\ImportStripeCustomers;
use App\Jobs\Util\StripeUpdatePaymentMethods;
use App\Models\CompanyGateway;

class StripeController extends BaseController
{

    private $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

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

		// return response()->json(['message' => 'Processing'], 200);


		if(auth()->user()->isAdmin())
		{
			
			ImportStripeCustomers::dispatch(auth()->user()->company());

			return response()->json(['message' => 'Processing'], 200);

		}
		
		return response()->json(['message' => 'Unauthorized'], 403);
	}

	public function verify()
	{
		
		if(auth()->user()->isAdmin())
		{

	    	$company_gateway = CompanyGateway::where('company_id', auth()->user()->company()->id)
	                            ->where('is_deleted',0)
	    						->whereIn('gateway_key', $this->stripe_keys)
	    						->first();

			return $company_gateway->driver(new Client)->verifyConnect();

		}

		return response()->json(['message' => 'Unauthorized'], 403);

	}
}
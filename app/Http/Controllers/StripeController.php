<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Jobs\Util\ImportStripeCustomers;
use App\Jobs\Util\StripeUpdatePaymentMethods;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;

class StripeController extends BaseController
{
    use MakesHash;

    private $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    public function update()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->isAdmin()) {
            StripeUpdatePaymentMethods::dispatch($user->company());

            return response()->json(['message' => 'Processing'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function import()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->isAdmin()) {
            ImportStripeCustomers::dispatch($user->company());

            return response()->json(['message' => 'Processing'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function verify()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->isAdmin()) {
            MultiDB::findAndSetDbByCompanyKey($user->company()->company_key);

            /** @var \App\Models\CompanyGateway $company_gateway */
            $company_gateway = CompanyGateway::where('company_id', $user->company()->id)
                                ->where('is_deleted', 0)
                                ->whereIn('gateway_key', $this->stripe_keys)
                                ->first();

            return $company_gateway->driver(new Client())->verifyConnect();
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function disconnect(string $company_gateway_id)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\CompanyGateway $company_gateway */
        $company_gateway = CompanyGateway::where('company_id', $user->company()->id)
                                         ->where('id', $this->decodePrimaryKey($company_gateway_id))
                                         ->whereIn('gateway_key', $this->stripe_keys)
                                         ->firstOrFail();

        return $company_gateway->driver()->disconnect();
    }
}

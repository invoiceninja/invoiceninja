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

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public int $company_gateway_id;

    public string $company_key;

    private int $client_id;

    public function __construct(string $company_key, int $company_gateway_id, int $client_id)
    {
        $this->company_key = $company_key;
        $this->company_gateway_id = $company_gateway_id;
        $this->client_id = $client_id;
    }

    public function handle()
    {

        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::where('company_key', $this->company_key)->first();

        if($company->id !== config('ninja.ninja_default_company_id'))
            return;

        $company_gateway = CompanyGateway::find($this->company_gateway_id);
        $client = Client::withTrashed()->find($this->client_id);

        $stripe = $company_gateway->driver($client)->init();

        $customer = $stripe->findOrCreateCustomer();
        //Else create a new record
        $data['name'] = $client->present()->name();
        $data['phone'] = substr($client->present()->phone(), 0, 20);

        $data['address']['line1'] = $client->address1;
        $data['address']['line2'] = $client->address2;
        $data['address']['city'] = $client->city;
        $data['address']['postal_code'] = $client->postal_code;
        $data['address']['state'] = $client->state;
        $data['address']['country'] = $client->country ? $client->country->iso_3166_2 : '';

        $data['shipping']['name'] = $client->present()->name();
        $data['shipping']['address']['line1'] = $client->shipping_address1;
        $data['shipping']['address']['line2'] = $client->shipping_address2;
        $data['shipping']['address']['city'] = $client->shipping_city;
        $data['shipping']['address']['postal_code'] = $client->shipping_postal_code;
        $data['shipping']['address']['state'] = $client->shipping_state;
        $data['shipping']['address']['country'] = $client->shipping_country ? $client->shipping_country->iso_3166_2 : '';

        \Stripe\Customer::update($customer->id, $data, $stripe->stripe_connect_auth);

    }
}

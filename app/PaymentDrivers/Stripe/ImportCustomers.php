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

namespace App\PaymentDrivers\Stripe;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Factory\ClientGatewayTokenFactory;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Country;
use App\Models\Currency;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Stripe\Customer;
use Stripe\PaymentMethod;

class ImportCustomers
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function run()
    {

        $this->stripe->init();

        $customers = Customer::all();

        foreach($customers as $customer)
        {
            $this->addCustomer($customer);
        }   

        /* Now call the update payment methods handler*/
        $this->stripe->updateAllPaymentMethods();

    }

    private function addCustomer(Customer $customer)
    {
        
        $account = $this->stripe->company_gateway->company->account;

        $existing_customer = $this->stripe
                                  ->company_gateway
                                  ->client_gateway_tokens()
                                  ->where('gateway_customer_reference', $customer->id)
                                  ->exists();


        if($existing_customer)
            return;

        $client = ClientFactory::create($this->stripe->company_gateway->company_id, $this->stripe->company_gateway->user_id);

        if(property_exists($customer, 'address'))
        {
            $client->address1 = property_exists($customer->address, 'line1') ? $customer->address->line1 : '';
            $client->address2 = property_exists($customer->address, 'line2') ? $customer->address->line2 : '';
            $client->city = property_exists($customer->address, 'city') ? $customer->address->city : '';
            $client->state = property_exists($customer->address, 'state') ? $customer->address->state : '';
            $client->phone =  property_exists($customer->address, 'phone') ? $customer->phone : '';

            if(property_exists($customer->address, 'country')){

                $country = Country::where('iso_3166_2', $customer->address->country)->first();

                if($country)
                    $client->country_id = $country->id;

            }
        }

        if($customer->currency) {

            $currency = Currency::where('code', $customer->currency)->first();

            if($currency){

                $settings = $client->settings;
                $settings->currency_id = (string)$currency->id;
                $client->settings = $settings;

            }

        }

        $client->name = property_exists($customer, 'name') ? $customer->name : '';

        if(!$account->isPaidHostedClient() && Client::where('company_id', $this->stripe->company_gateway->company_id)->count() <= config('ninja.quotas.free.clients')){

            $client->save();

            $contact = ClientContactFactory::create($client->company_id, $client->user_id);
            $contact->client_id = $client->id;
            $contact->first_name = $client->name ?: '';
            $contact->phone = $client->phone ?: '';
            $contact->email = $client->email ?: '';
            $contact->save();
            
        }
    }
}

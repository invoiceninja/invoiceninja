<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Client;

use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class PaymentMethod
{
    use MakesHash;

    /** @var \Illuminate\Support\Collection<CompanyGateway> $gateways **/
    private $gateways;

    private $payment_methods;

    private $payment_urls = [];

    public function __construct(private Client $client, private float $amount)
    {
    }

    public function run()
    {
        $this->getGateways()
             ->getMethods();

        return $this->getPaymentUrls();
    }

    public function getPaymentUrls()
    {
        $pu = collect($this->payment_urls);
        $keys = $pu->pluck('gateway_type_id');
        $contains_both = $keys->contains('1') && $keys->contains('29'); //handle the case where PayPal Advanced cards + regular CC is present

        $this->payment_urls = $pu->when($contains_both, function ($methods) {
            return $methods->reject(function ($item) {
                return $item['gateway_type_id'] == '29';
            });
        })->toArray();

        return $this->payment_urls;

    }

    public function getPaymentMethods()
    {
        return $this->payment_methods;
    }

    private function getGateways()
    {
        $company_gateways = $this->client->getSetting('company_gateway_ids');

        // We need to check for "0" here as we disable a payment gateway for a client with the number "0"
        if ($company_gateways || $company_gateways == '0') {
            $transformed_ids = $this->transformKeys(explode(',', $company_gateways));

            //gateways disabled
            if($company_gateways == '0') {
                $transformed_ids = [];
            }

            $this->gateways = $this->client
                             ->company
                             ->company_gateways
                             ->whereIn('id', $transformed_ids)
                             ->where('is_deleted', false)
                             ->whereNull('deleted_at')
                             ->where('gateway_key', '!=', '54faab2ab6e3223dbe848b1686490baa')
                             ->sortby(function ($model) use ($transformed_ids) { //company gateways are sorted in order of priority
                                 return array_search($model->id, $transformed_ids); // this closure sorts for us
                             });

        } else {
            $this->gateways = CompanyGateway::query()
                             ->with('gateway')
                             ->where('company_id', $this->client->company_id)
                             ->where('gateway_key', '!=', '54faab2ab6e3223dbe848b1686490baa')
                             ->whereNull('deleted_at')
                             ->where('is_deleted', false)->get();
        }

        return $this;
    }

    private function getCustomGateways()
    {
        $company_gateways = $this->client->getSetting('company_gateway_ids');

        //we need to check for "0" here as we disable a payment gateway for a client with the number "0"
        if ($company_gateways || $company_gateways == '0') {
            $transformed_ids = $this->transformKeys(explode(',', $company_gateways));

            if($company_gateways == '0') {
                $transformed_ids = [];
            }

            $this->gateways = $this->client
                             ->company
                             ->company_gateways
                             ->whereIn('id', $transformed_ids)
                             ->where('is_deleted', false)
                             ->whereNull('deleted_at')
                             ->where('gateway_key', '54faab2ab6e3223dbe848b1686490baa')
                             ->sortby(function ($model) use ($transformed_ids) { //company gateways are sorted in order of priority
                                 return array_search($model->id, $transformed_ids); // this closure sorts for us
                             });
        } else {
            $this->gateways = CompanyGateway::query()
                             ->with('gateway')
                             ->where('company_id', $this->client->company_id)
                             ->where('gateway_key', '54faab2ab6e3223dbe848b1686490baa')
                             ->whereNull('deleted_at')
                             ->where('is_deleted', false)->get();
        }

        return $this;
    }

    private function getMethods()
    {
        $this->payment_methods = [];

        foreach ($this->gateways as $gateway) {
            //if gateway doesn't exist or is not implemented - continue here //todo
            if (! $gateway->driver($this->client)) {
                continue;
            }

            foreach ($gateway->driver($this->client)->gatewayTypes() as $type) {
                if (isset($gateway->fees_and_limits) && is_object($gateway->fees_and_limits) && property_exists($gateway->fees_and_limits, $type)) {
                    if ($this->validGatewayForAmount($gateway->fees_and_limits->{$type}) && $gateway->fees_and_limits->{$type}->is_enabled) {
                        $this->payment_methods[] = [$gateway->id => $type];
                    }
                } else {
                }
            }
        }

        //transform from Array to Collection
        $payment_methods_collections = collect($this->payment_methods);

        //** Plucks the remaining keys into its own collection
        $this->payment_methods = $payment_methods_collections->intersectByKeys($payment_methods_collections->flatten(1)->unique());

        //@15-06-2024
        foreach($this->payment_methods as $key => $type) {
            foreach ($type as $gateway_id => $gateway_type_id) {
                $gate = $this->gateways->where('id', $gateway_id)->first();
                $this->buildUrl($gate, $gateway_type_id);
            }
        }

        //@15-06-2024
        $this->payment_methods = [];

        /* Loop through custom gateways if any exist and append them to the methods collection*/
        $this->getCustomGateways();

        //note we have to use GatewayType::CREDIT_CARD as alias for CUSTOM
        foreach ($this->gateways as $gateway) {
            foreach ($gateway->driver($this->client)->gatewayTypes() as $type) {
                if (isset($gateway->fees_and_limits) && is_object($gateway->fees_and_limits) && property_exists($gateway->fees_and_limits, GatewayType::CREDIT_CARD)) { //@phpstan-ignore-line
                    if ($this->validGatewayForAmount($gateway->fees_and_limits->{GatewayType::CREDIT_CARD}, $this->amount)) {
                        $this->buildUrl($gateway, $type);
                    }
                } else {
                    $this->buildUrl($gateway, null);
                }
            }
        }

        if (($this->client->getSetting('use_credits_payment') == 'option' || $this->client->getSetting('use_credits_payment') == 'always') && $this->client->service()->getCreditBalance() > 0) {
            // Show credits as only payment option if both statements are true.
            if (
                $this->client->service()->getCreditBalance() > $this->amount
                && $this->client->getSetting('use_credits_payment') == 'always') {
                $payment_urls = [];
            }

            $this->payment_urls[] = [
                'label' => ctrans('texts.apply_credit'),
                'company_gateway_id'  => CompanyGateway::GATEWAY_CREDIT,
                'gateway_type_id' => GatewayType::CREDIT,
                'is_paypal' => false,
            ];
        }

        return $this;
    }


    //@15-06-2024
    private function buildUrl(CompanyGateway $gateway, ?int $type = null)
    {

        $fee_label = $gateway->calcGatewayFeeLabel($this->amount, $this->client, $type);

        if (! $type || (GatewayType::CUSTOM == $type)) {
            $this->payment_urls[] = [
                'label' => $gateway->getConfigField('name').$fee_label,
                'company_gateway_id'  => $gateway->id,
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'is_paypal' => $gateway->isPayPal(),
            ];
        } else {
            $this->payment_urls[] = [
                'label' => $gateway->getTypeAlias($type).$fee_label,
                'company_gateway_id'  => $gateway->id,
                'gateway_type_id' => $type,
                'is_paypal' => $gateway->isPayPal(),
            ];
        }

        return $this;
    }

    private function validGatewayForAmount($fees_and_limits_for_payment_type): bool
    {
        if (isset($fees_and_limits_for_payment_type)) {
            $fees_and_limits = $fees_and_limits_for_payment_type;
        } else {
            return true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $fees_and_limits->min_limit != -1 && ($this->amount < $fees_and_limits->min_limit && $this->amount != -1)) {
            return false;
        }

        if ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $fees_and_limits->max_limit != -1 && ($this->amount > $fees_and_limits->max_limit && $this->amount != -1)) {
            return false;
        }

        return true;
    }
}

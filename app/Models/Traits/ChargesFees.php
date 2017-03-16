<?php

namespace App\Models\Traits;

use App\Models\GatewayType;
use App\Models\InvoiceItem;
use App\Models\AccountGatewaySettings;

/**
 * Class ChargesFees
 */
trait ChargesFees
{
    public function calcGatewayFee($gatewayTypeId = false, $includeTax = true)
    {
        $account = $this->account;
        $settings = $account->getGatewaySettings($gatewayTypeId);
        $fee = 0;

        if (! $settings) {
            return false;
        }

        if ($settings->fee_amount) {
            $fee += $settings->fee_amount;
        }

        if ($settings->fee_percent) {
            // prevent charging taxes twice on the surcharge
            $amount = $this->amount;
            $taxField = $account->gateway_fee_location == FEE_LOCATION_CHARGE1 ? 'custom_taxes1' : 'custom_taxes1';
            if ($this->$taxField) {
                $taxAmount = 0;
                foreach ($this->getTaxes() as $key => $tax) {
                    $taxAmount += $tax['amount'];
                }
                $amount -= $taxAmount;
            }

            $fee += $amount * $settings->fee_percent / 100;
        }

        if ($account->gateway_fee_location == FEE_LOCATION_ITEM && $includeTax) {
            $preTaxFee = $fee;

            if ($settings->fee_tax_rate1) {
                $fee += $preTaxFee * $settings->fee_tax_rate1 / 100;
            }

            if ($settings->fee_tax_rate2) {
                $fee += $preTaxFee * $settings->fee_tax_rate2 / 100;
            }
        }

        return round($fee, 2);
    }
}

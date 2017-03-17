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
    public function calcGatewayFee($gatewayTypeId = false, $includeTax = false)
    {
        $account = $this->account;
        $settings = $account->getGatewaySettings($gatewayTypeId);
        $taxField = $account->gateway_fee_location == FEE_LOCATION_CHARGE1 ? 'custom_taxes1' : 'custom_taxes1';
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
            if ($this->$taxField) {
                $taxAmount = 0;
                foreach ($this->getTaxes() as $key => $tax) {
                    $taxAmount += $tax['amount'];
                }
                $amount -= $taxAmount;
            }

            $fee += $amount * $settings->fee_percent / 100;
        }

        // calculate final amount with tax
        if ($includeTax && $this->$taxField) {
            $preTaxFee = $fee;
            if (floatval($this->tax_rate1)) {
                $fee += round($preTaxFee * $this->tax_rate1 / 100, 2);
            }
            if (floatval($this->tax_rate2)) {
                $fee += round($preTaxFee * $this->tax_rate2 / 100, 2);
            }
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

    public function getGatewayFeeItem()
    {
        if (! $this->relationLoaded('invoice_items')) {
            $this->load('invoice_items');
        }

        foreach ($this->invoice_items as $item) {
            if ($item->invoice_item_type_id == INVOICE_ITEM_TYPE_PENDING_GATEWAY_FEE) {
                return $item;
            }
        }

        return false;
    }
}

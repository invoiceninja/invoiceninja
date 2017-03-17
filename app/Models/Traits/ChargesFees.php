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
        $location = $account->gateway_fee_location;
        $taxField = $location == FEE_LOCATION_CHARGE1 ? 'custom_taxes1' : 'custom_taxes1';
        $fee = 0;

        if (! $settings) {
            return false;
        }

        if ($settings->fee_amount) {
            $fee += $settings->fee_amount;
        }

        if ($settings->fee_percent) {
            $amount = $this->partial > 0 ? $this->partial : $this->balance;

            // prevent charging taxes twice on the surcharge
            if ($location != FEE_LOCATION_ITEM) {
                if ($this->$taxField) {
                    $taxAmount = 0;
                    foreach ($this->getTaxes() as $key => $tax) {
                        $taxAmount += $tax['amount'] - $tax['paid'];
                    }
                    $amount -= $taxAmount;
                }
            }

            $fee += $amount * $settings->fee_percent / 100;
        }

        // calculate final amount with tax
        if ($includeTax) {
            if ($location == FEE_LOCATION_ITEM) {
                $preTaxFee = $fee;

                if ($settings->fee_tax_rate1) {
                    $fee += $preTaxFee * $settings->fee_tax_rate1 / 100;
                }

                if ($settings->fee_tax_rate2) {
                    $fee += $preTaxFee * $settings->fee_tax_rate2 / 100;
                }
            } elseif ($this->$taxField) {
                $preTaxFee = $fee;
                if (floatval($this->tax_rate1)) {
                    $fee += round($preTaxFee * $this->tax_rate1 / 100, 2);
                }
                if (floatval($this->tax_rate2)) {
                    $fee += round($preTaxFee * $this->tax_rate2 / 100, 2);
                }
            }
        }

        return round($fee, 2);
    }

    public function getGatewayFee()
    {
        $account = $this->account;
        $location = $account->gateway_fee_location;

        if (! $location) {
            return 0;
        }

        if ($location == FEE_LOCATION_ITEM) {
            $item = $this->getGatewayFeeItem();
            return $item ? $item->amount() : 0;
        } else {
            return $this->$location;
        }
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

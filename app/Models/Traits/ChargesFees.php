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
        $fee = 0;

        if (! $account->gateway_fee_enabled) {
            return false;
        }

        if ($settings->fee_amount) {
            $fee += $settings->fee_amount;
        }

        if ($settings->fee_percent) {
            $amount = $this->partial > 0 ? $this->partial : $this->balance;
            $fee += $amount * $settings->fee_percent / 100;
        }

        // calculate final amount with tax
        if ($includeTax) {
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

    public function getGatewayFee()
    {
        $account = $this->account;

        if (! $account->gateway_fee_enabled) {
            return 0;
        }

        $item = $this->getGatewayFeeItem();
        return $item ? $item->amount() : 0;
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

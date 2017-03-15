<?php

namespace App\Models\Traits;

use App\Models\InvoiceItem;
use App\Models\AccountGatewaySettings;

/**
 * Class ChargesFees
 */
trait ChargesFees
{
    public function calcGatewayFee($gatewayTypeId = false, $includeTax = true)
    {
        $settings = $this->account->getGatewaySettings($gatewayTypeId);
        $fee = 0;

        if (! $settings) {
            return false;
        }

        if ($settings->fee_amount) {
            $fee += $settings->fee_amount;
        }

        if ($settings->fee_percent) {
            $amount = $this->amount;
            if ($item = $this->getGatewayFeeItem()) {
                $amount -= $item->amount();
            }
            $fee += $amount * $settings->fee_percent / 100;
        }

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

    public function getGatewayFeeItem()
    {
        foreach ($this->invoice_items as $item) {
            if ($item->invoice_item_type_id == INVOICE_ITEM_TYPE_GATEWAY_FEE) {
                return $item;
            }
        }

        return false;
    }

    public function getGatewayFee()
    {
        $item = $this->getGatewayFeeItem();

        return $item ? $item->cost : 0;
    }

    public function setGatewayFee($gatewayTypeId)
    {
        $settings = $this->account->getGatewaySettings($gatewayTypeId);
        $fee = $this->calcGatewayFee($gatewayTypeId);
        $feePreTax = $this->calcGatewayFee($gatewayTypeId, false);
        $item = $this->getGatewayFeeItem();

        if ($fee == ($item ? $item->amount() : 0)) {
            return;
        }

        if ($item) {
            $this->amount -= $item->amount();
            $this->balance -= $item->amount();
            if (! $fee) {
                $item->forceDelete();
            }
        }

        if ($fee) {
            $item = $item ?: InvoiceItem::createNew($this);
            $item->invoice_item_type_id = INVOICE_ITEM_TYPE_GATEWAY_FEE;
            $item->product_key = trans('texts.fee');
            $item->notes = '';
            $item->cost = $feePreTax;
            $item->qty = 1;
            $item->tax_rate1 = $settings->fee_tax_rate1;
            $item->tax_name1 = $settings->fee_tax_name1;
            $item->tax_rate2 = $settings->fee_tax_rate2;
            $item->tax_name2 = $settings->fee_tax_name2;
            $this->invoice_items()->save($item);
        }

        $this->amount += $fee;
        $this->balance += $fee;
        $this->save();
    }
}

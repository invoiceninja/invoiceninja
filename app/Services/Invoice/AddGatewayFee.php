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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;

class AddGatewayFee extends AbstractService
{
    private $company_gateway;

    private $invoice;

    private $amount;

    private $gateway_type_id;

    public function __construct(CompanyGateway $company_gateway, int $gateway_type_id, Invoice $invoice, float $amount)
    {
        $this->company_gateway = $company_gateway;

        $this->invoice = $invoice;

        $this->amount = $amount;

        $this->gateway_type_id = $gateway_type_id;
    }

    public function run()
    {
        $gateway_fee = round($this->company_gateway->calcGatewayFee($this->amount, $this->gateway_type_id, $this->invoice->uses_inclusive_taxes), $this->invoice->client->currency()->precision);

        if (!$gateway_fee) 
            return $this->invoice;

        // Removes existing stale gateway fees
        $this->cleanPendingGatewayFees();

        // If a gateway fee is > 0 insert the line item
        if ($gateway_fee > 0) 
            return $this->processGatewayFee($gateway_fee);
        
        // If we have reached this far, then we are apply a gateway discount    
        return $this->processGatewayDiscount($gateway_fee);
    }

    private function cleanPendingGatewayFees()
    {
        $invoice_items = $this->invoice->line_items;

        $invoice_items = collect($invoice_items)->filter(function ($item) {
            return $item->type_id != '3';
        });

        $this->invoice->line_items = $invoice_items;

        return $this;
    }

    private function processGatewayFee($gateway_fee)
    {
        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.surcharge');
        $invoice_item->notes = ctrans('texts.online_payment_surcharge');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits($this->gateway_type_id)) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
        }

        $invoice_items = $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $this->invoice = $this->invoice->calc()->getInvoice();

        return $this->invoice;
    }

    private function processGatewayDiscount($gateway_fee)
    {
        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.discount');
        $invoice_item->notes = ctrans('texts.online_payment_discount');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits()) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
        }

        $invoice_items = $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        $this->invoice = $this->invoice->calc()->getInvoice();

        return $this->invoice;
    }
}

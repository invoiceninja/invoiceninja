<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class AddGatewayFee extends AbstractService
{

    private $company_gateway;

    private $invoice;

    private $amount;

    public function __construct(CompanyGateway $company_gateway, Invoice $invoice, float $amount)
    {
        $this->company_gateway = $company_gateway;
        
        $this->invoice = $invoice;

        $this->amount = $amount;
    }

    public function run()
    {
        $gateway_fee = $this->company_gateway->calcGatewayFee($this->amount);

        if($gateway_fee > 0)
            return $this->processGatewayFee($gateway_fee);

        return $this->processGatewayDiscount($gateway_fee);


    }

    private function processGatewayFee($gateway_fee)
    {
        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = 3;
        $invoice_item->notes = ctrans('texts.Gateway Fee Surcharge');
    }

    private function processGatewayDiscount($gateway_fee)
    {
        
    }
}

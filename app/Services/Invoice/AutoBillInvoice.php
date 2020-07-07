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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class AutoBillInvoice extends AbstractService
{

    private $invoice;

    private $client; 

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    
        $this->client = $invoice->client;
    }

    public function run()
    {

        if(!$invoice->isPayable())
            return $invoice;


    }

    private function getGateway($amount)
    {
        $gateway_tokens = $this->client->gateway_tokens->orderBy('is_default', 'DESC');

        $gateways->filter(function ($method) use ($amount) {
            if ($method->min_limit !==  null && $amount < $method->min_limit) {
                return false;
            }

            if ($method->max_limit !== null && $amount > $method->min_limit) {
                return false;
            }
        });
    }
}

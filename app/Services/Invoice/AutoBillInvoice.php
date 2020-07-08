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

        $billing_gateway_token = null;

        $gateway_tokens->filter(function ($token) use ($amount){

            return $this->validGatewayLimits($token, $amount);

        })->all()->first();

    }

    /**
     * Checks whether a given gateway token is able
     * to process the payment after passing through the
     * fees and limits check
     *     
     * @param  CompanyGateway $cg     The CompanyGateway instance
     * @param  float          $amount The amount to be paid
     * @return bool
     */
    public function validGatewayLimits($cg, $amount) : bool
    {
        if(isset($cg->fees_and_limits))
            $fees_and_limits = $cg->fees_and_limits->{"1"};
        else
            $passes = true;

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !==  null && $amount < $fees_and_limits->min_limit) {
            info("amount {$amount} less than ". $fees_and_limits->min_limit);
            $passes = false;   
        }
        else if ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !==  null && $amount > $fees_and_limits->max_limit){ 
            info("amount {$amount} greater than ". $fees_and_limits->max_limit);
            $passes = false;
        }
        else
            $passes = true;

        return $passes;
    }

}

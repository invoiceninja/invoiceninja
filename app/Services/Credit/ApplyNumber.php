<?php

namespace App\Services\Credit;

use App\Models\Credit;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Customer\UpdateCustomerBalance;
use App\Jobs\Customer\UpdateCustomerPaidToDate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Customer\CustomerService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $credit;

    public function __construct(Client $client, Credit $credit)
    {
        $this->client = $client;
        
        $this->credit = $credit;
    }

    public function run()
    {

        if ($this->credit->number != '') {
            return $this->credit;
        }

        $this->credit->number = $this->getNextCreditNumber($this->client);


        return $this->credit;
    }
}

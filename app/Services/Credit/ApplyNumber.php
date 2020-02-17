<?php

namespace App\Services\Credit;

use App\Credit;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
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

    private $customer;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function run($credit)
    {

        if ($credit->number != '') {
            return $credit;
        }

        $credit->number = $this->getNextCreditNumber($this->client);


        return $credit;
    }
}

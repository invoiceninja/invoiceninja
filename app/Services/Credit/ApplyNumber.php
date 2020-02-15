<?php

namespace App\Services\Credit;

use App\Credit;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Customer\UpdateCustomerBalance;
use App\Jobs\Customer\UpdateCustomerPaidToDate;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Customer\CustomerService;
use App\Services\Payment\PaymentService;
use App\Traits\GeneratesCounter;

class ApplyNumber
{
    use GeneratesCounter;

    private $customer;

    public function __construct($customer)
    {
        $this->customer = $customer;
    }

    public function __invoke($credit)
    {

        if ($credit->number != '') {
            return $credit;
        }

        $credit->number = $this->getNextCreditNumber($this->customer);


        return $credit;
    }
}

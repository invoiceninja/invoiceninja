<?php

namespace App\Services\Payment;

use App\Exceptions\PaymentRefundFailed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Activity;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;

class DeletePayment
{
    public $payment;

    private $activity_repository;

    public function __construct($payment)
    {
        $this->payment = $payment;

        $this->activity_repository = new ActivityRepository();
    }

    public function run()
    {

        return $this->setStatus() //sets status of payment
            ->updateCreditables() //return the credits first
            ->updatePaymentables() //update the paymentable items
            ->adjustInvoices()
            ->save();
    }

    //

    //reverse paymentables->invoices
    
    //reverse paymentables->credits
    
    //set refunded to amount 

    //set applied amount to 0


    /**
     * Saves the payment
     * 
     * @return Payment $payment
     */
    private function save()
    {
        $this->payment->save();

        return $this->payment;
    }


}

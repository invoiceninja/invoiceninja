<?php

namespace App\Listeners;

use App\Events\PaymentWasDeleted;
use App\Models\Credit;
use App\Ninja\Repositories\CreditRepository;
use Carbon;

/**
 * Class CreditListener.
 */
class CreditListener
{
    /**
     * @var CreditRepository
     */
    protected $creditRepo;

    /**
     * CreditListener constructor.
     *
     * @param CreditRepository $creditRepo
     */
    public function __construct(CreditRepository $creditRepo)
    {
        $this->creditRepo = $creditRepo;
    }

    /**
     * @param PaymentWasDeleted $event
     */
    public function deletedPayment(PaymentWasDeleted $event)
    {
        $payment = $event->payment;

        // if the payment was from a credit we need to refund the credit
        if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT) {
            return;
        }

        $credit = Credit::createNew();
        $credit->client_id = $payment->client_id;
        $credit->credit_date = Carbon::now()->toDateTimeString();
        $credit->balance = $credit->amount = $payment->getCompletedAmount();
        $credit->private_notes = trans('texts.refunded_credit_payment');
        $credit->save();
    }
}

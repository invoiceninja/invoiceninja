<?php namespace App\Listeners;

use Carbon;
use App\Models\Credit;
use App\Events\PaymentWasDeleted;
use App\Events\PaymentWasRefunded;
use App\Ninja\Repositories\CreditRepository;

/**
 * Class CreditListener
 */
class CreditListener
{
    /**
     * @var CreditRepository
     */
    protected $creditRepo;

    /**
     * CreditListener constructor.
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
        $credit->private_notes = $payment->transaction_reference;
        $credit->save();
    }

    /**
     * @param PaymentWasRefunded $event
     */
    public function refundedPayment(PaymentWasRefunded $event)
    {
        $payment = $event->payment;

        // if the payment was from a credit we need to refund the credit
        if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT) {
            return;
        }

        $credit = Credit::createNew();
        $credit->client_id = $payment->client_id;
        $credit->credit_date = Carbon::now()->toDateTimeString();
        $credit->balance = $credit->amount = $event->refundAmount;
        $credit->private_notes = $payment->transaction_reference;
        $credit->save();
    }
}

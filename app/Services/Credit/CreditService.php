<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Credit;

use App\Factory\PaymentFactory;
use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Util\UnlinkFile;
use App\Models\Credit;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Repositories\CreditRepository;
use App\Repositories\PaymentRepository;
use App\Services\Credit\CreateInvitations;
use App\Services\Credit\TriggeredActions;
use App\Utils\Traits\MakesHash;

class CreditService
{
    use MakesHash;

    public $credit;

    public function __construct($credit)
    {
        $this->credit = $credit;
    }

    public function getCreditPdf($invitation)
    {
        return (new GetCreditPdf($invitation))->run();
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $this->credit = (new ApplyNumber($this->credit->client, $this->credit))->run();

        return $this;
    }

    public function createInvitations()
    {
        $this->credit = (new CreateInvitations($this->credit))->run();

        return $this;
    }

    public function setStatus($status)
    {
        $this->credit->status_id = $status;

        return $this;
    }

    public function sendEmail($contact = null)
    {
        $send_email = new SendEmail($this->credit, null, $contact);

        return $send_email->run();
    }

    public function setCalculatedStatus()
    {
        if ((int) $this->credit->balance == 0) {
            $this->credit->status_id = Credit::STATUS_APPLIED;
        } elseif ((string) $this->credit->amount == (string) $this->credit->balance) {
            $this->credit->status_id = Credit::STATUS_SENT;
        } elseif ($this->credit->balance > 0) {
            $this->credit->status_id = Credit::STATUS_PARTIAL;
        }

        return $this;
    }

    /*
        For euro users - we mark a credit as paid when
        we need to document a refund of sorts.

        Criteria: Credit must be a negative value
                  A negative payment for the balance will be generated
                  This amount will be reduced from the clients paid to date.

    */
    public function markPaid()
    {
        if ($this->credit->balance > 0) {
            return $this;
        }

        $this->markSent();

        $payment_repo = new PaymentRepository(new CreditRepository());

        //set credit balance to zero
        $adjustment = $this->credit->balance;

        $this->updateBalance($adjustment)
             ->updatePaidToDate($adjustment)
             ->setStatus(Credit::STATUS_APPLIED)
             ->save();

        //create a negative payment of total $this->credit->balance
        $payment = PaymentFactory::create($this->credit->company_id, $this->credit->user_id);
        $payment->client_id = $this->credit->client_id;
        $payment->amount = $adjustment;
        $payment->applied = $adjustment;
        $payment->refunded = 0;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::CREDIT;
        $payment->is_manual = true;
        $payment->currency_id = $this->credit->client->getSetting('currency_id');
        $payment->date = now();

        $payment->saveQuietly();
        $payment->number = $payment->client->getNextPaymentNumber($payment->client, $payment);
        $payment = $payment_repo->processExchangeRates(['client_id' => $this->credit->client_id], $payment);
        $payment->saveQuietly();

        $payment
             ->credits()
             ->attach($this->credit->id, ['amount' => $adjustment]);

        //reduce client paid_to_date by $this->credit->balance amount
        // $this->credit
        //      ->client
        //      ->service()
        //      ->updatePaidToDate($adjustment)
        //      ->save();

        $client = $this->credit->client->fresh();
        $client->service()
                ->updatePaidToDate($adjustment)
                ->save();

        event('eloquent.created: App\Models\Payment', $payment);

        return $this;
    }

    public function markSent()
    {
        $this->credit = (new MarkSent($this->credit->client, $this->credit))->run();

        return $this;
    }

    public function applyPayment($invoice, $amount, $payment)
    {
        $this->credit = (new ApplyPayment($this->credit, $invoice, $amount, $payment))->run();

        $this->deletePdf();

        return $this;
    }

    public function adjustBalance($adjustment)
    {
        $this->credit->balance += $adjustment;

        return $this;
    }

    public function updatePaidToDate($adjustment)
    {
        $this->credit->paid_to_date += $adjustment;

        return $this;
    }

    public function updateBalance($adjustment)
    {
        $this->credit->balance -= $adjustment;

        return $this;
    }

    /**
     * Sometimes we need to refresh the
     * PDF when it is updated etc.
     * @return InvoiceService
     */
    public function touchPdf($force = false)
    {
        try {
            if ($force) {
                $this->credit->invitations->each(function ($invitation) {
                    CreateEntityPdf::dispatchSync($invitation);
                });

                return $this;
            }

            $this->credit->invitations->each(function ($invitation) {
                CreateEntityPdf::dispatch($invitation);
            });
        } catch (\Exception $e) {
            nlog('failed creating invoices in Touch PDF');
        }

        return $this;
    }

    public function fillDefaults()
    {
        $settings = $this->credit->client->getMergedSettings();

        if (! $this->credit->design_id) {
            $this->credit->design_id = $this->decodePrimaryKey($settings->credit_design_id);
        }

        if (! isset($this->credit->footer)) {
            $this->credit->footer = $settings->credit_footer;
        }

        if (! isset($this->credit->terms)) {
            $this->credit->terms = $settings->credit_terms;
        }

        /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        if (! isset($this->credit->exchange_rate) && $this->credit->client->currency()->id != (int) $this->credit->company->settings->currency_id) {
            $this->credit->exchange_rate = $this->credit->client->currency()->exchange_rate;
        }

        if (! isset($this->credit->public_notes)) {
            $this->credit->public_notes = $this->credit->client->public_notes;
        }

        return $this;
    }

    public function deletePdf()
    {
        $this->credit->invitations->each(function ($invitation) {
            UnlinkFile::dispatchSync(config('filesystems.default'), $this->credit->client->credit_filepath($invitation).$this->credit->numberFormatter().'.pdf');
        });

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->invoice = (new TriggeredActions($this->credit, $request))->run();

        return $this;
    }

    /**
     * Saves the credit.
     * @return Credit object
     */
    public function save() : ?Credit
    {
        $this->credit->saveQuietly();

        return $this->credit;
    }
}

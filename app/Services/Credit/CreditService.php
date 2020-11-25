<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Credit;

use App\Models\Credit;
use App\Services\Credit\ApplyPayment;
use App\Services\Credit\CreateInvitations;
use App\Services\Credit\MarkSent;
use App\Services\Credit\SendEmail;
use App\Utils\Traits\MakesHash;

class CreditService
{
    use MakesHash;

    protected $credit;

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
        if ((int)$this->credit->balance == 0) {
            $this->credit->status_id = Credit::STATUS_APPLIED;
        } elseif ((string)$this->credit->amount == (string)$this->credit->balance) {
            $this->credit->status_id = Credit::STATUS_SENT;
        } elseif ($this->credit->balance > 0) {
            $this->credit->status_id = Credit::STATUS_PARTIAL;
        }

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

        return $this;
    }

    public function adjustBalance($adjustment)
    {
        $this->credit->balance += $adjustment;

        return $this;
    }


    public function fillDefaults()
    {
        $settings = $this->credit->client->getMergedSettings();

        if (! $this->credit->design_id) {
            $this->credit->design_id = $this->decodePrimaryKey($settings->credit_design_id);
        }
            
        if (!isset($this->credit->footer)) {
            $this->credit->footer = $settings->credit_footer;
        }

        if (!isset($this->credit->terms)) {
            $this->credit->terms = $settings->credit_terms;
        }

        
        return $this;
    }
    
    /**
     * Saves the credit.
     * @return Credit object
     */
    public function save() : ?Credit
    {
        $this->credit->save();

        return $this->credit;
    }
}

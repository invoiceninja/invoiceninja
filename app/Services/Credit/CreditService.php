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
use App\Services\Credit\CreateInvitations;
use App\Services\Credit\MarkSent;

class CreditService
{
    protected $credit;

    public function __construct($credit)
    {
        $this->credit = $credit;
    }

    public function getCreditPdf($contact)
    {
        return (new GetCreditPdf($this->credit, $contact))->run();
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

    public function markSent()
    {
        $this->credit = (new MarkSent($this->credit->client, $this->credit))->run();

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

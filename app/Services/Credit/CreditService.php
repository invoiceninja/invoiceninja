<?php
namespace App\Services\Credit;

use App\Models\Credit;

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
     * Applies the invoice number
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

    /**
     * Saves the credit
     * @return Credit object
     */
    public function save() : ?Credit
    {
        $this->credit->save();

        return $this->credit;
    }
}

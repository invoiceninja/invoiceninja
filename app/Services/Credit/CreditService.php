<?php
namespace App\Services\Credit;

use App\Credit;

class CreditService
{
    protected $credit;


    public function __construct($credit)
    {
        $this->credit = $credit;

    }

    public function getCreditPdf($contact)
    {
        $get_invoice_pdf = new GetCreditPdf();

        return $get_invoice_pdf($this->credit, $contact);
    }

    /**
     * Applies the invoice number
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $apply_number = new ApplyNumber($this->credit->customer);

        $this->credit = $apply_number($this->credit);

        return $this;
    }

    public function createInvitations()
    {
        $create_invitation = new CreateInvitations();

        $this->invoice = $create_invitation($this->invoice);

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

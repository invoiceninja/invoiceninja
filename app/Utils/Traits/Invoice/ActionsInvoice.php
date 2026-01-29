<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Invoice;

use App\Models\Invoice;

trait ActionsInvoice
{

    public function invoicePayable($invoice): bool
    {
        if($invoice->company->verifactuEnabled() && $invoice->amount < 0) {
            return false;
        }
        elseif($invoice->is_deleted) {
            return false;
        }
        elseif(in_array($invoice->status_id, [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])) {
            return false;
        }
        elseif ($invoice->status_id == Invoice::STATUS_PAID) {
            return false;
        } 
        elseif ($invoice->status_id == Invoice::STATUS_DRAFT) {
            return true;
        } 
        elseif (in_array($invoice->status_id, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]) && $invoice->balance != 0) {
            return true;
        } 

        return false;
        
    }
    
    public function invoiceDeletable($invoice): bool
    {
        //Cancelled invoices are not deletable if verifactu is enabled
        if($invoice->company->verifactuEnabled() && $invoice->status_id == Invoice::STATUS_DRAFT && $invoice->is_deleted == false) {
            return true;
        }
        elseif($invoice->company->verifactuEnabled()) {
            return false;
        }

        if (!$invoice->is_deleted)
            return true;
        
        return false;
    }

    public function invoiceRestorable($invoice): bool
    {
        if($invoice->company->verifactuEnabled() && !$invoice->is_deleted && $invoice->deleted_at) {
            return true;
        }
        elseif($invoice->company->verifactuEnabled()) {
            return false;
        }

        return !is_null($invoice->deleted_at);
        
    }

    public function invoiceCancellable($invoice): bool
    {
        if($invoice->company->verifactuEnabled() && 
        $invoice->backup->document_type === 'F1' && 
        $invoice->backup->child_invoice_ids->count() == 0 &&
        in_array($invoice->status_id, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]) &&
        $invoice->is_deleted == false) 
        {
            return true;
        }
        elseif($invoice->company->verifactuEnabled()){
            return false;
        }

        if (in_array($invoice->status_id, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]) &&
             $invoice->is_deleted == false &&
             $invoice->deleted_at == null) {
            return true;
        }

        return false;
    }

    public function invoiceReversable($invoice): bool
    {
        if($invoice->company->verifactuEnabled()){
            return false;
        }

        if (($invoice->status_id == Invoice::STATUS_SENT ||
             $invoice->status_id == Invoice::STATUS_PARTIAL ||
             $invoice->status_id == Invoice::STATUS_CANCELLED ||
             $invoice->status_id == Invoice::STATUS_PAID) &&
             $invoice->is_deleted == false &&
             $invoice->deleted_at == null) {
            return true;
        }

        return false;
    }
}

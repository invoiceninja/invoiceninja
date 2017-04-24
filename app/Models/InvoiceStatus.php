<?php

namespace App\Models;

use Eloquent;

/**
 * Class InvoiceStatus.
 */
class InvoiceStatus extends Eloquent
{
    /**
     * @var bool
     */
    public $timestamps = false;

    public static function getIdFromAlias($status)
    {
        switch ($status) {
            case 'draft':
                return INVOICE_STATUS_DRAFT;
            case 'sent':
                return INVOICE_STATUS_SENT;
            case 'viewed':
                return INVOICE_STATUS_VIEWED;
            case 'approved':
                return INVOICE_STATUS_APPROVED;
            case 'partial':
                return INVOICE_STATUS_PARTIAL;
            case 'overdue':
                return INVOICE_STATUS_OVERDUE;
            case 'unpaid':
                return INVOICE_STATUS_UNPAID;
            default:
                return false;
        }        
    }
}

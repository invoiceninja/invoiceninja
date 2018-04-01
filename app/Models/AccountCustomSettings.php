<?php

namespace App\Models;

use Eloquent;

/**
 * Class Account.
 */
class AccountCustomSettings extends Eloquent
{
    /**
     * @var array
     */
    protected $fillable = [
        'custom_label1',
        'custom_value1',
        'custom_label2',
        'custom_value2',
        'custom_client_label1',
        'custom_client_label2',
        'custom_invoice_label1',
        'custom_invoice_label2',
        'custom_invoice_taxes1',
        'custom_invoice_taxes2',
        'custom_invoice_text_label1',
        'custom_invoice_text_label2',
        'custom_invoice_item_label1',
        'custom_invoice_item_label2',        
    ];

}

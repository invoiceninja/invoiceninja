<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed|string action_class
 * @property array parameters
 * @property string action_name
 * @property integer scheduler_id
 * @property integer company_id
 */
class ScheduledJob extends Model
{
    use HasFactory;

    const CREATE_CLIENT_REPORT = 'create_client_report';
    const CREATE_CLIENT_CONTACT_REPORT = 'create_client_contact_report';
    const CREATE_CREDIT_REPORT = 'create_credit_report';
    const CREATE_DOCUMENT_REPORT = 'create_document_report';
    const CREATE_EXPENSE_REPORT = 'create_expense_report';
    const CREATE_INVOICE_ITEM_REPORT = 'create_invoice_item_report';
    const CREATE_INVOICE_REPORT = 'create_invoice_report';
    const CREATE_PAYMENT_REPORT = 'create_payment_report';
    const CREATE_PRODUCT_REPORT = 'create_product_report';
    const CREATE_PROFIT_AND_LOSS_REPORT = 'create_profit_and_loss_report';
    const CREATE_QUOTE_ITEM_REPORT ='create_quote_item_report';
    const CREATE_QUOTE_REPORT = 'create_quote_report';
    const CREATE_RECURRING_INVOICE_REPORT = 'create_recurring_invoice_report';
    const CREATE_TASK_REPORT = 'create_task_report';


    protected $fillable = ['action_class', 'action_name', 'parameters', 'scheduler_id','company_id'];
    protected $casts = [
        'scheduled_run' => 'date',
        'parameters' => 'array'
    ];
}

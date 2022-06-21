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

namespace App\Transformers;

use App\Models\Document;
use App\Models\RecurringExpense;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * class RecurringExpenseTransformer.
 */
class RecurringExpenseTransformer extends EntityTransformer
{
    use MakesHash;
    use SoftDeletes;

    protected $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
        'documents',
    ];

    public function includeDocuments(RecurringExpense $recurring_expense)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($recurring_expense->documents, $transformer, Document::class);
    }

    /**
     * @param RecurringExpense $recurring_expense
     *
     * @return array
     */
    public function transform(RecurringExpense $recurring_expense)
    {
        $data = [
            'id' => $this->encodePrimaryKey($recurring_expense->id),
            'user_id' => $this->encodePrimaryKey($recurring_expense->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($recurring_expense->assigned_user_id),
            'status_id' => (string) ($recurring_expense->status_id ?: 1),
            'vendor_id' => $this->encodePrimaryKey($recurring_expense->vendor_id),
            'invoice_id' => $this->encodePrimaryKey($recurring_expense->invoice_id),
            'client_id' => $this->encodePrimaryKey($recurring_expense->client_id),
            'bank_id' => (string) $recurring_expense->bank_id ?: '',
            'invoice_currency_id' => (string) $recurring_expense->invoice_currency_id ?: '',
            'recurring_expense_currency_id' => '', //todo remove redundant in 5.0.25
            'currency_id' => (string) $recurring_expense->currency_id ?: '',
            'category_id' => $this->encodePrimaryKey($recurring_expense->category_id),
            'payment_type_id' => (string) $recurring_expense->payment_type_id ?: '',
            'recurring_recurring_expense_id' => (string) $recurring_expense->recurring_recurring_expense_id ?: '',
            'is_deleted' => (bool) $recurring_expense->is_deleted,
            'should_be_invoiced' => (bool) $recurring_expense->should_be_invoiced,
            'invoice_documents' => (bool) $recurring_expense->invoice_documents,
            'amount' => (float) $recurring_expense->amount ?: 0,
            'foreign_amount' => (float) $recurring_expense->foreign_amount ?: 0,
            'exchange_rate' => (float) $recurring_expense->exchange_rate ?: 0,
            'tax_name1' => $recurring_expense->tax_name1 ? $recurring_expense->tax_name1 : '',
            'tax_rate1' => (float) $recurring_expense->tax_rate1,
            'tax_name2' => $recurring_expense->tax_name2 ? $recurring_expense->tax_name2 : '',
            'tax_rate2' => (float) $recurring_expense->tax_rate2,
            'tax_name3' => $recurring_expense->tax_name3 ? $recurring_expense->tax_name3 : '',
            'tax_rate3' => (float) $recurring_expense->tax_rate3,
            'private_notes' => (string) $recurring_expense->private_notes ?: '',
            'public_notes' => (string) $recurring_expense->public_notes ?: '',
            'transaction_reference' => (string) $recurring_expense->transaction_reference ?: '',
            'transaction_id' => (string) $recurring_expense->transaction_id ?: '',
            'date' => $recurring_expense->date ?: '',
            'number' => (string) $recurring_expense->number ?: '',
            'payment_date' => $recurring_expense->payment_date ?: '',
            'custom_value1' => $recurring_expense->custom_value1 ?: '',
            'custom_value2' => $recurring_expense->custom_value2 ?: '',
            'custom_value3' => $recurring_expense->custom_value3 ?: '',
            'custom_value4' => $recurring_expense->custom_value4 ?: '',
            'updated_at' => (int) $recurring_expense->updated_at,
            'archived_at' => (int) $recurring_expense->deleted_at,
            'created_at' => (int) $recurring_expense->created_at,
            'project_id' => $this->encodePrimaryKey($recurring_expense->project_id),
            'tax_amount1' => (float) $recurring_expense->tax_amount1,
            'tax_amount2' => (float) $recurring_expense->tax_amount2,
            'tax_amount3' => (float) $recurring_expense->tax_amount3,
            'uses_inclusive_taxes' => (bool) $recurring_expense->uses_inclusive_taxes,
            'calculate_tax_by_amount' => (bool) $recurring_expense->calculate_tax_by_amount,
            'entity_type' => 'recurringExpense',
            'frequency_id' => (string) $recurring_expense->frequency_id,
            'remaining_cycles' => (int) $recurring_expense->remaining_cycles,
            'last_sent_date' => $recurring_expense->last_sent_date ?: '',
            // 'next_send_date' => $recurring_expense->next_send_date ?: '',
            'next_send_date' => $recurring_expense->next_send_date_client ?: '',
            'recurring_dates' => (array) [],
        ];

        if (request()->has('show_dates') && request()->query('show_dates') == 'true') {
            $data['recurring_dates'] = (array) $recurring_expense->recurringDates();
        }

        return $data;
    }
}

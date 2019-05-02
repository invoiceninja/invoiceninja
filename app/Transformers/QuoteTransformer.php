<?php

namespace App\Transformers;

use App\Models\Quote;
use App\Utils\Traits\MakesHash;

/**
 * @SWG\Definition(definition="quote", required={"quote_number"}, @SWG\Xml(name="quote"))
 */
class QuoteTransformer extends EntityTransformer
{
    use MakesHash;
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="amount", type="number", format="float", example=10, readOnly=true)
    * @SWG\Property(property="balance", type="number", format="float", example=10, readOnly=true)
    * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
    * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
    * @SWG\Property(property="is_deleted", type="boolean", example=false, readOnly=true)
    * @SWG\Property(property="client_id", type="integer", example=1)
    * @SWG\Property(property="status_id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="quote_number", type="string", example="0001")
    * @SWG\Property(property="discount", type="number", format="float", example=10)
    * @SWG\Property(property="po_number", type="string", example="0001")
    * @SWG\Property(property="quote_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="due_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="terms", type="string", example="sample")
    * @SWG\Property(property="private_notes", type="string", example="Notes")
    * @SWG\Property(property="public_notes", type="string", example="Notes")
    * @SWG\Property(property="quote_type_id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="is_recurring", type="boolean", example=false)
    * @SWG\Property(property="frequency_id", type="integer", example=1)
    * @SWG\Property(property="start_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="end_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="last_sent_date", type="string", format="date", example="2018-01-01", readOnly=true)
    * @SWG\Property(property="recurring_quote_id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="tax_name1", type="string", example="VAT")
    * @SWG\Property(property="tax_name2", type="string", example="Upkeep")
    * @SWG\Property(property="tax_rate1", type="number", format="float", example="17.5")
    * @SWG\Property(property="tax_rate2", type="number", format="float", example="30.0")
    * @SWG\Property(property="is_amount_discount", type="boolean", example=false)
    * @SWG\Property(property="quote_footer", type="string", example="Footer")
    * @SWG\Property(property="partial", type="number",format="float", example=10)
    * @SWG\Property(property="partial_due_date", type="string", format="date", example="2018-01-01")
    * @SWG\Property(property="has_tasks", type="boolean", example=false, readOnly=true)
    * @SWG\Property(property="auto_bill", type="boolean", example=false)
    * @SWG\Property(property="custom_value1", type="number",format="float", example=10)
    * @SWG\Property(property="custom_value2", type="number",format="float", example=10)
    * @SWG\Property(property="custom_taxes1", type="boolean", example=false)
    * @SWG\Property(property="custom_taxes2", type="boolean", example=false)
    * @SWG\Property(property="has_expenses", type="boolean", example=false, readOnly=true)
    * @SWG\Property(property="quote_quote_id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="custom_text_value1", type="string", example="Custom Text Value")
    * @SWG\Property(property="custom_text_value2", type="string", example="Custom Text Value")
    * @SWG\Property(property="is_quote", type="boolean", example=false, readOnly=true)
    * @SWG\Property(property="is_public", type="boolean", example=false)
    * @SWG\Property(property="filename", type="string", example="Filename", readOnly=true)
    */
    protected $defaultIncludes = [
    //    'quote_items',
    ];

    protected $availableIncludes = [
    //    'invitations',
    //    'payments',
    //    'client',
    //    'documents',
    ];

/*
    public function includequoteItems(quote $quote)
    {
        $transformer = new quoteItemTransformer($this->serializer);

        return $this->includeCollection($quote->quote_items, $transformer, ENTITY_quote_ITEM);
    }

    public function includeInvitations(quote $quote)
    {
        $transformer = new InvitationTransformer($this->account, $this->serializer);

        return $this->includeCollection($quote->invitations, $transformer, ENTITY_INVITATION);
    }

    public function includePayments(quote $quote)
    {
        $transformer = new PaymentTransformer($this->account, $this->serializer, $quote);

        return $this->includeCollection($quote->payments, $transformer, ENTITY_PAYMENT);
    }

    public function includeClient(quote $quote)
    {
        $transformer = new ClientTransformer($this->account, $this->serializer);

        return $this->includeItem($quote->client, $transformer, ENTITY_CLIENT);
    }

    public function includeExpenses(quote $quote)
    {
        $transformer = new ExpenseTransformer($this->account, $this->serializer);

        return $this->includeCollection($quote->expenses, $transformer, ENTITY_EXPENSE);
    }

    public function includeDocuments(quote $quote)
    {
        $transformer = new DocumentTransformer($this->account, $this->serializer);

        $quote->documents->each(function ($document) use ($quote) {
            $document->setRelation('quote', $quote);
        });

        return $this->includeCollection($quote->documents, $transformer, ENTITY_DOCUMENT);
    }
*/
    public function transform(Quote $quote)
    {
        return [
            'id' => $this->encodePrimaryKey($quote->id),
            'amount' => (float) $quote->amount,
            'balance' => (float) $quote->balance,
            'client_id' => (int) $quote->client_id,
            'status_id' => (int) ($quote->status_id ?: 1),
            'updated_at' => $quote->updated_at,
            'archived_at' => $quote->deleted_at,
            'quote_number' => $quote->quote_number,
            'discount' => (float) $quote->discount,
            'po_number' => $quote->po_number,
            'quote_date' => $quote->quote_date ?: '',
            'valid_until' => $quote->valid_until ?: '',
            'terms' => $quote->terms ?: '',
            'public_notes' => $quote->public_notes ?: '',
            'private_notes' => $quote->private_notes ?: '',
            'is_deleted' => (bool) $quote->is_deleted,
            'quote_type_id' => (int) $quote->quote_type_id,
            'tax_name1' => $quote->tax_name1 ? $quote->tax_name1 : '',
            'tax_rate1' => (float) $quote->tax_rate1,
            'tax_name2' => $quote->tax_name2 ? $quote->tax_name2 : '',
            'tax_rate2' => (float) $quote->tax_rate2,
            'is_amount_discount' => (bool) ($quote->is_amount_discount ?: false),
            'quote_footer' => $quote->quote_footer ?: '',
            'partial' => (float) ($quote->partial ?: 0.0),
            'partial_due_date' => $quote->partial_due_date ?: '',
            'custom_value1' => (float) $quote->custom_value1,
            'custom_value2' => (float) $quote->custom_value2,
            'custom_taxes1' => (bool) $quote->custom_taxes1,
            'custom_taxes2' => (bool) $quote->custom_taxes2,
            'has_tasks' => (bool) $quote->has_tasks,
            'has_expenses' => (bool) $quote->has_expenses,
            'custom_text_value1' => $quote->custom_text_value1 ?: '',
            'custom_text_value2' => $quote->custom_text_value2 ?: '',
            'backup' => $quote->backup ?: '',
            'settings' => $quote->settings,
        ];
    }
}
<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class EntityEmailHistoryRequest extends Request
{
    use MakesHash;

    private const ENTITY_CLASSES = [
        'invoice' => Invoice::class,
        'quote' => Quote::class,
        'credit' => Credit::class,
        'recurring_invoice' => RecurringInvoice::class,
        'purchase_order' => PurchaseOrder::class,
    ];

    private string $entity_plural = 'invoices';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        //handle authorization in controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'entity' => ['bail', 'required', Rule::in(array_keys(self::ENTITY_CLASSES))],
            'entity_id' => ['bail', 'required', Rule::exists($this->entity_plural, 'id')->where('company_id', $user->company()->id)],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('entity_id', $input)) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        if (isset($input['entity'])) {
            $entity_class = self::ENTITY_CLASSES[$input['entity']] ?? $input['entity'];

            if (in_array($entity_class, self::ENTITY_CLASSES, true)) {
                $this->entity_plural = (new $entity_class())->getTable();
            }
        }

        $this->replace($input);
    }

    public function getEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        $entity = match($this->input('entity')) {
            'invoice' => Invoice::class,
            'quote' => Quote::class,
            'credit' => Credit::class,
            'recurring_invoice' => RecurringInvoice::class,
            // 'purchase_order' => PurchaseOrder::class,
            default => null,
        };

        if($entity){

            return $entity::withTrashed()->where('company_id', $user->company()->id)->where('id', $this->input('entity_id'))->first();
        }

        return $entity;
    }
}

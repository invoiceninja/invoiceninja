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

namespace App\Http\Requests\ProductAllocation;

use App\Http\Requests\Request;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Validation\Rule;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateProductAllocationRequest extends Request
{
    use ChecksEntityStatus;

    protected $subscription;
    protected $recurring;
    protected $project;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->product_allocation);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['client_id'] = [
            'nullable',
            Rule::exists('clients', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];
        $rules['recurring_id'] = [
            'nullable',
            Rule::exists('recurring_invoices', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];
        $rules['project_id'] = [
            'nullable',
            Rule::exists('projects', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];
        $rules['subscription_id'] = [
            'nullable',
            Rule::exists('subscriptions', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];
        $rules['invoice_id'] = [
            'nullable',
            Rule::exists('invoices', 'id')->where('status', Invoice::STATUS_DRAFT)->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];

        $rules['quantity'] = 'sometimes|numeric';
        $rules['should_be_invoiced'] = 'sometimes|bool';
        $rules['invoice_aggregation_key'] = 'sometimes|string|not_in:invoice-product-mapper';

        $rules['from'] = 'nullable|date';
        $rules['until'] = 'nullable|date';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('company_id', $input)) {
            unset($input['company_id']);
        }

        if (array_key_exists('product_id', $input)) {
            unset($input['product_id']);
        }

        if (array_key_exists('equipment_id', $input)) {
            unset($input['equipment_id']);
        }

        if (array_key_exists('recurring_id', $input) && is_string($input['recurring_id'])) {
            $input['recurring_id'] = $this->decodePrimaryKey($input['recurring_id']);
        }

        if (isset($input['product_id'])) {
            $this->product = Product::find($input['product_id']);

            if (isset($this->product)) {

                // PRODUCT_ALLOCATION_TYPE_TIME_BASED => validate/calculate quantity
                if ($this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && isset($input['from']) && isset($input['until'])) {
                    if ($this->product->unit_of_measure == 'M')
                        $input['quantity'] = $input['from']->diffInMinutes($input['until']);
                    else if ($this->product->unit_of_measure == 'H')
                        $input['quantity'] = $input['from']->diffInHours($input['until']);
                    else if ($this->product->unit_of_measure == 'D')
                        $input['quantity'] = $input['from']->diffInDays($input['until']);
                }

            }

        }

        if (isset($input['subscription_id'])) {
            $this->subscription = Subscription::find($input['subscription_id']);

            if (isset($this->subscription)) {

                $input['client_id'] = $this->subscription->client_id;
                $input['project_id'] = $this->subscription->project_id;
                $input['recurring_id'] = $this->subscription->recurring_id;

            }

        } elseif (isset($input['recurring_id'])) {
            $this->recurring = RecurringInvoice::find($input['recurring_id']);

            if (isset($this->recurring)) {

                $input['client_id'] = $this->recurring->client_id;
                $input['project_id'] = $this->recurring->project_id;

            }

        } elseif (isset($input['project_id'])) {
            $this->project = Project::find($input['project_id']);

            if (isset($this->project)) {

                $input['client_id'] = $this->project->client_id;

            }

        }

        $this->replace($input);
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $input = $this->all();

            // quantity validity
            if ($this->product && $this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED && (!isset($input['quantity']) || $input['quantity'] <= 0))
                $validator->errors()->add('quantity', '0 not allowed in quantity based allocation.');
            if ($this->product && $this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && isset($input['quantity']) && $input['quantity'] != 0)
                $validator->errors()->add('quantity', 'Quantity is computed automaticly.');

            // from/until validity
            if ($this->product && $this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && !isset($input['from']))
                $validator->errors()->add('from', 'Required for time based allocations.');
            if (isset($input['from']) && isset($input['until']) && $input['from']->gt($input['until']))
                $validator->errors()->add('until', 'Has to be after from.');

            if (
                $this->subscription && $this->subscription->client_id != $input['client_id'] ||
                $this->subscription->project_id != $input['project_id'] ||
                $this->subscription->recurring_id != $input['recurring_id']
            ) {
                $validator->errors()->add('subscription_id', 'Subscription does not match provided data.');
            }

            if (
                $this->recurring && $this->recurring->client_id != $input['client_id'] ||
                $this->recurring->project_id != $input['project_id']
            ) {
                $validator->errors()->add('recurring_id', 'Recurring invoice does not match provided data.');
            }

            if ($this->project && $this->project->client_id != $input['client_id']) {
                $validator->errors()->add('project_id', 'Project does not belong to client.');
            }
        });
    }
}

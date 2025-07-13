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
use App\Models\ProductAllocation;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreProductAllocationRequest extends Request
{

    protected $subscription;
    protected $recurring;
    protected $project;
    protected $product;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', ProductAllocation::class);
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


        $rules['product_id'] = [
            'required',
            Rule::exists('products', 'id')->whereNotNull('allocation_type')->where('company_id', $user->company()->id)->where('is_deleted', 0),
        ];

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
            Rule::exists('invoices', 'id')->where('status_id', Invoice::STATUS_DRAFT)->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];

        $rules['equipment_id'] = [
            'nullable',
            Rule::exists('product_equipments', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)
        ];

        $rules['quantity'] = 'sometimes|numeric';
        $rules['should_be_invoiced'] = 'sometimes|bool';
        $rules['invoice_aggregation_key'] = 'nullable|string|not_in:invoice-product-mapper';

        $rules['from'] = 'nullable|date';
        $rules['until'] = 'nullable|date';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['product_id'] ??= null;
        $input['client_id'] ??= null;
        $input['project_id'] ??= null;
        $input['invoice_id'] ??= null;
        $input['recurring_id'] ??= null;
        $input['subscription_id'] ??= null;
        $input['equipment_id'] ??= null;
        $input['quantity'] ??= 0;
        $input['from'] = array_key_exists('from', $input) && isset($input['from']) ? Carbon::parse($input['from']) : null;
        $input['until'] = array_key_exists('until', $input) && isset($input['until']) ? Carbon::parse($input['until']) : null;
        $input['equipment_id'] ??= null;
        $input['invoice_aggregation_key'] ??= null;

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('product_id', $input) && is_string($input['product_id'])) {
            $input['product_id'] = $this->decodePrimaryKey($input['product_id']);
        } else if (array_key_exists('product_key', $input) && is_string($input['product_key'])) {
            $input['product_id'] = Product::where('product_key', $input['product_key'])->first()->id;
        }

        if (array_key_exists('recurring_id', $input) && is_string($input['recurring_id'])) {
            $input['recurring_id'] = $this->decodePrimaryKey($input['recurring_id']);
        }

        if (array_key_exists('equipment_id', $input) && is_string($input['equipment_id'])) {
            $input['equipment_id'] = $this->decodePrimaryKey($input['equipment_id']);
        }

        if (isset($input['product_id'])) {
            $this->product = Product::find($input['product_id']);

            if (isset($this->product)) {

                // PRODUCT_ALLOCATION_TYPE_TIME_BASED => validate/calculate quantity
                if ($this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED) {

                    if (isset($input['from']) && isset($input['until'])) {
                        if ($this->product->unit_of_measure == 'M')
                            $input['quantity'] = $input['from']->diffInMinutes($input['until']);
                        else if ($this->product->unit_of_measure == 'H')
                            $input['quantity'] = $input['from']->diffInHours($input['until']);
                        else if ($this->product->unit_of_measure == 'D')
                            $input['quantity'] = $input['from']->diffInDays($input['until']);
                    } else
                        $input['quantity'] = 0;

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

            /** @var \App\Models\User $user */
            $user = auth()->user();

            // equipment_id
            if ($this->product && $this->product->allocation_equipment_required == true && !isset($input['equipment_id']))
                $validator->errors()->add('equipment_id', 'Required by product configuration.');
            if ($this->product && $this->product->allocation_equipment_required == false && isset($input['equipment_id']))
                $validator->errors()->add('equipment_id', 'Not Allowed by product configuration.');

            // quantity validity
            if ($this->product && $this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_QUANTITY_BASED && (!isset($input['quantity']) || $input['quantity'] <= 0))
                $validator->errors()->add('quantity', '0 not allowed in quantity based allocation.');

            // from/until validity
            if ($this->product && $this->product->allocation_type == Product::PRODUCT_ALLOCATION_TYPE_TIME_BASED && !isset($input['from']))
                $validator->errors()->add('from', 'Required for time based allocations.');
            if (isset($input['from']) && isset($input['until']) && $input['from']->gt($input['until']))
                $validator->errors()->add('until', 'Has to be after from.');


            if (
                $this->subscription && (
                    $this->subscription->client_id != $input['client_id'] ||
                    $this->subscription->project_id != $input['project_id'] ||
                    $this->subscription->recurring_id != $input['recurring_id']
                )
            ) {
                $validator->errors()->add('subscription_id', 'Subscription does not match provided data.');
            }

            if (
                $this->recurring && (
                    $this->recurring->client_id != $input['client_id'] ||
                    $this->recurring->project_id != $input['project_id']
                )
            ) {
                $validator->errors()->add('recurring_id', 'Recurring invoice does not match provided data.');
            }

            if ($this->project && $this->project->client_id != $input['client_id']) {
                $validator->errors()->add('project_id', 'Project does not belong to client.');
            }
        });
    }
}

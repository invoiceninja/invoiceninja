<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\TaskScheduler;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Scheduler\ValidClientIds;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreSchedulerRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [
            'name' => 'bail|sometimes|nullable|string',
            'is_paused' => 'bail|sometimes|boolean',
            'frequency_id' => 'bail|sometimes|integer|digits_between:1,12',
            'next_run' => 'bail|required|date:Y-m-d|after_or_equal:today',
            'next_run_client' => 'bail|sometimes|date:Y-m-d',
            'template' => 'bail|required|string',
            'parameters' => 'bail|array',
            'parameters.clients' => ['bail','sometimes', 'array', new ValidClientIds()],
            'parameters.date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom',
            'parameters.start_date' => ['bail', 'sometimes', 'date:Y-m-d', 'required_if:parameters.date_rate,custom'],
            'parameters.end_date' => ['bail', 'sometimes', 'date:Y-m-d', 'required_if:parameters.date_rate,custom', 'after_or_equal:parameters.start_date'],
            'parameters.entity' => ['bail', 'sometimes', 'string', 'in:invoice,credit,quote,purchase_order'],
            'parameters.entity_id' => ['bail', 'sometimes', 'string'],
            'parameters.report_name' => ['bail','sometimes', 'string', 'required_if:template,email_report', 'in:ar_detailed,ar_summary,client_balance,tax_summary,profitloss,client_sales,user_sales,product_sales,clients,client_contacts,credits,documents,expenses,invoices,invoice_items,quotes,quote_items,recurring_invoices,payments,products,tasks'],
            'parameters.date_key' => ['bail','sometimes', 'string'],
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('next_run', $input) && is_string($input['next_run'])) {
            $input['next_run_client'] = $input['next_run'];
        }
     
        if($input['template'] == 'email_record'){
            $input['frequency_id'] = 0;
        }

        $this->replace($input);
    }
}

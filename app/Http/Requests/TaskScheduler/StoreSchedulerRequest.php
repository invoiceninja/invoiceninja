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

namespace App\Http\Requests\TaskScheduler;

use App\Models\Design;
use App\Models\Invoice;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use App\Http\ValidationRules\Scheduler\ValidClientIds;
use App\Http\ValidationRules\Scheduler\InvoiceWithNoExistingSchedule;

class StoreSchedulerRequest extends Request
{
    use MakesHash;
    public array $client_statuses = [
                        'all',
                        'draft',
                        'paid',
                        'unpaid',
                        'overdue',
                        'pending',
                        'invoiced',
                        'logged',
                        'partial',
                        'applied',
                        'active',
                        'paused',
                        'completed',
                        'approved',
                        'expired',
                        'upcoming',
                        'converted',
                        'uninvoiced',
    ];
    
    public array $templates = [
        'invoice',
        'quote',
        'credit',
        'purchase_order',
        'quote',
        'credit',
        'purchase_order',
        'invoice',
        'reminder1',
        'reminder2',
        'reminder3',
        'reminder_endless',
        'custom1',
        'custom2',
        'custom3',
    ];
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin();
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
            'parameters.date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom,all',
            'parameters.start_date' => ['bail', 'date:Y-m-d', 'required_if:parameters.date_range,custom'],
            'parameters.end_date' => ['bail', 'date:Y-m-d', 'required_if:parameters.date_range,custom', 'after_or_equal:parameters.start_date'],
            'parameters.entity' => ['bail', 'sometimes', 'string', 'in:invoice,credit,quote,purchase_order'],
            'parameters.entity_id' => ['bail', 'sometimes', 'string'],
            'parameters.report_name' => ['bail','sometimes', 'string', 'required_if:template,email_report','in:vendor,purchase_order_item,purchase_order,ar_detailed,ar_summary,client_balance,tax_summary,profitloss,client_sales,user_sales,product_sales,activity,activities,client,clients,client_contact,client_contacts,credit,credits,document,documents,expense,expenses,invoice,invoices,invoice_item,invoice_items,quote,quotes,quote_item,quote_items,recurring_invoice,recurring_invoices,payment,payments,product,products,task,tasks'],
            'parameters.date_key' => ['bail','sometimes', 'string'],
            'parameters.status' => ['bail','sometimes', 'nullable', 'string'],
            'parameters.include_project_tasks' => ['bail','sometimes', 'boolean', 'required_if:template,invoice_outstanding_tasks'],
            'parameters.auto_send' => ['bail','sometimes', 'boolean', 'required_if:template,invoice_outstanding_tasks'],
            'parameters.invoice_id' => ['bail', 'string', 'required_if:template,payment_schedule', new InvoiceWithNoExistingSchedule()],
            'parameters.auto_bill' => ['bail', 'boolean', 'required_if:template,payment_schedule'],
            'parameters.template' => ['bail', 'sometimes', 'nullable', 'string', Rule::in($this->templates)],
            'parameters.schedule' => ['bail', 'array', 'required_if:template,payment_schedule', 'min:1'],
            'parameters.schedule.*.id' => ['bail','sometimes', 'integer'],
            'parameters.schedule.*.date' => ['bail','sometimes', 'date:Y-m-d'],
            'parameters.schedule.*.amount' => ['bail','sometimes', 'numeric'],
            'parameters.schedule.*.is_amount' => ['bail','sometimes', 'boolean'],
            'parameters.template_id' => ['bail','sometimes', 'string', 'nullable'],
        ];

        return $rules;
    }

    public function withValidator(\Illuminate\Validation\Validator $validator)
    {
        $validator->after(function ($validator) {
            if(!empty($this->parameters['template_id']) && Design::where('id', $this->decodePrimaryKey($this->parameters['template_id']))->where('is_template',true)->company()->doesntExist()) {
                $validator->errors()->add('template_id', 'Invalid Template ID Selected');
            }
        });
    }
    
    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('next_run', $input) && is_string($input['next_run'])) {
            $input['next_run_client'] = $input['next_run'];
        }

        if ($input['template'] == 'email_record') {
            $input['frequency_id'] = 0;
        }

        if (isset($input['parameters']) && !isset($input['parameters']['clients'])) {
            $input['parameters']['clients'] = [];
        }

        if (isset($input['parameters']['status'])) {

            $task_statuses = [];

            if (isset($input['parameters']['report_name']) && $input['parameters']['report_name'] == 'task') {
                $task_statuses = array_diff(explode(",", $input['parameters']['status']), $this->client_statuses);
            }

            $input['parameters']['status'] = collect(explode(",", $input['parameters']['status']))
                                                    ->filter(function ($status) {
                                                        return in_array($status, $this->client_statuses);
                                                    })->merge($task_statuses)
                                                    ->implode(",") ?? '';

        }

        if(isset($input['parameters']['schedule']) && is_array($input['parameters']['schedule']) && count($input['parameters']['schedule']) > 0) {
            $input['remaining_cycles'] = count($input['parameters']['schedule']);
        }

        if($input['template'] == 'payment_schedule' && isset($input['parameters']['invoice_id'])){
            $i = Invoice::withTrashed()->find($this->decodePrimaryKey($input['parameters']['invoice_id']));
            $input['name'] = ctrans('texts.payment_schedule'). " " . ctrans('texts.invoice_number_short') . " " . $i->number;
        }
        elseif($input['template'] == 'invoice_outstanding_tasks'){
            $input['name'] = ctrans('texts.invoice_outstanding_tasks');
        }

        $input['parameters']['user_id'] = auth()->user()->id;

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'parameters.schedule.min' => 'The schedule must have at least one item.',
            'parameters.schedule' => 'You must have at least one schedule entry.',
            'parameters.invoice_id.required_if' => 'The invoice is required for the payment schedule template.'
        ];
    }
}
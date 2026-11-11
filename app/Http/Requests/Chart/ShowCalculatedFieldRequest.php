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

namespace App\Http\Requests\Chart;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesDates;
use Illuminate\Validation\Validator;

class ShowCalculatedFieldRequest extends Request
{
    use MakesDates;

    /** @var array<int, string> */
    private const TASK_DURATION_FIELDS = [
        'task_estimated_duration',
        'task_remaining_estimated_duration',
    ];

    /** @var array<int, string> */
    private const TASK_COUNT_FIELDS = [
        'unestimated_tasks',
        'tasks_over_estimate',
        'overdue_tasks',
        'tasks_due',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /**@var \App\Models\User auth()->user */
        $user = auth()->user();

        return $user->isAdmin() || $user->hasPermission('view_dashboard');
    }

    public function rules(): array
    {
        return [
            'date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom',
            'start_date' => 'bail|sometimes|date',
            'end_date' => 'bail|sometimes|date',
            'field' => 'required|bail|in:active_invoices,outstanding_invoices,completed_payments,refunded_payments,active_quotes,unapproved_quotes,logged_tasks,invoiced_tasks,paid_tasks,task_estimated_duration,task_remaining_estimated_duration,unestimated_tasks,tasks_over_estimate,overdue_tasks,tasks_due,logged_expenses,pending_expenses,invoiced_expenses,invoice_paid_expenses',
            'calculation' => 'required|bail|in:sum,avg,count',
            'period' => 'required|bail|in:current,previous,total',
            'format' => 'sometimes|bail|in:time,money',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('field') || $validator->errors()->has('calculation') || $validator->errors()->has('format')) {
                    return;
                }

                $field = $this->input('field');

                if (in_array($field, self::TASK_DURATION_FIELDS, true)) {
                    if (! in_array($this->input('calculation'), ['sum', 'avg'], true)) {
                        $validator->errors()->add('calculation', 'The calculation must be sum or avg for task duration fields.');
                    }

                    if ($this->input('format') !== 'time') {
                        $validator->errors()->add('format', 'The format must be time for task duration fields.');
                    }
                }

                if (in_array($field, self::TASK_COUNT_FIELDS, true)) {
                    if ($this->input('calculation') !== 'count') {
                        $validator->errors()->add('calculation', 'The calculation must be count for task count fields.');
                    }

                    if ($this->has('format')) {
                        $validator->errors()->add('format', 'The format field is not supported for task count fields.');
                    }
                }
            },
        ];
    }

    public function prepareForValidation(): void
    {

        /**@var \App\Models\User auth()->user */
        $user = auth()->user();

        $input = $this->all();

        if (isset($input['date_range'])) {
            $dates = $this->calculateStartAndEndDates($input, $user->company());
            $input['start_date'] = $dates[0];
            $input['end_date'] = $dates[1];
        }

        if (! isset($input['start_date'])) {
            $input['start_date'] = now()->subDays(20)->format('Y-m-d');
        }

        if (! isset($input['end_date'])) {
            $input['end_date'] = now()->format('Y-m-d');
        }

        if (isset($input['period']) && $input['period'] == 'previous') {
            $dates = $this->calculatePreviousPeriodStartAndEndDates($input, $user->company());
            $input['start_date'] = $dates[0];
            $input['end_date'] = $dates[1];
        }

        $this->replace($input);
    }
}

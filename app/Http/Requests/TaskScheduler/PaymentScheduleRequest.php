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

namespace App\Http\Requests\TaskScheduler;

use App\Utils\BcMath;
use App\Http\Requests\Request;
use App\Models\RecurringQuote;
use Illuminate\Support\Carbon;
use App\Models\RecurringInvoice;

class PaymentScheduleRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
        return [
            'next_run' => 'required|date:Y-m-d',
            'frequency_id' => 'sometimes|integer|required_with:remaining_cycles',
            'remaining_cycles' => 'sometimes|integer|required_with:frequency_id',
            'parameters' => 'bail|array',
            'parameters.schedule' => 'array|required_without:frequency_id,remaining_cycles',
            'parameters.schedule.*.id' => 'required|integer',
            'parameters.schedule.*.date' => 'required|date:Y-m-d',
            'parameters.schedule.*.amount' => 'required|numeric',
            'parameters.schedule.*.is_amount' => 'required|boolean',
            'parameters.invoice_id' => 'required|string',
            'parameters.auto_bill' => 'required|boolean',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['parameters']['invoice_id'] = $this->invoice->hashed_id;
        $input['template'] = 'payment_schedule';
        $input['name'] = "Payment Schedule for Invoice #{$this->invoice->number}";
        $input['is_paused'] = false;
        $input['parameters']['auto_bill'] = (bool) isset($input['parameters']['auto_bill']) ? $input['parameters']['auto_bill'] : false;

        if (isset($input['parameters']['schedule']) && is_array($input['parameters']['schedule']) && count($input['parameters']['schedule']) > 0) {
            $input['parameters']['schedule'] = $input['parameters']['schedule'];
        } else {
            $input['parameters']['schedule'] = [];
        }

        if (isset($input['schedule']) && is_array($input['schedule']) && count($input['schedule']) > 0) {
            $schedule_map = collect($input['schedule'])->map(function ($schedule, $key) {
                return [
                    'id' => $key,
                    'date' => $schedule['date'],
                    'amount' => $schedule['amount'],
                    'is_amount' => $schedule['is_amount'],
                ];
            });

            $first_map = $schedule_map->first();

            if ($first_map['is_amount'] && !BcMath::equal($schedule_map->sum('amount'), $this->invoice->amount)) {
                $validator = \Validator::make([], []);
                $validator->errors()->add('schedule', 'The total amount of the schedule does not match the invoice amount.');
                throw new \Illuminate\Validation\ValidationException($validator);
            } elseif (!$first_map['is_amount'] && !BcMath::equal($schedule_map->sum('amount'), 100)) {
                $validator = \Validator::make([], []);
                $validator->errors()->add('schedule', 'The total percentage amount of the schedule does not match 100%.');
                throw new \Illuminate\Validation\ValidationException($validator);
            } else {
                $input['parameters']['schedule'] = $schedule_map->toArray();
            }

        }

        if (isset($input['frequency_id']) && isset($input['remaining_cycles'])) {
            $due_date = $input['next_run'] ?? $this->invoice->due_date ?? Carbon::parse($this->invoice->date)->addDays((int) $this->invoice->client->getSetting('payment_terms'));
            $input['parameters']['schedule'] = $this->generateSchedule($input['frequency_id'], $input['remaining_cycles'], Carbon::parse($due_date));
        }

        $input['remaining_cycles'] = count($input['parameters']['schedule']);

        $input['next_run_client'] = $input['next_run'];
        $input['next_run'] = Carbon::parse($input['next_run'])->addSeconds($this->invoice->company->timezone_offset())->format('Y-m-d');

        $this->replace($input);
    }

    private function generateSchedule(int $frequency_id, int $remaining_cycles, Carbon $due_date)
    {


        $amount = round($this->invoice->amount / $remaining_cycles, 2);

        $delta = round($amount * $remaining_cycles, 2);
        $adjustment = 0;

        if (!BcMath::equal($delta, $this->invoice->amount)) {
            $adjustment = round(floatval($this->invoice->amount) - floatval($delta), 2); //adjustment to make the total amount equal to the invoice amount
        }

        $schedule = [];

        for ($i = 0; $i < $remaining_cycles; $i++) {
            $schedule[] = [
                'id' => $i + 1,
                'date' => $i === 0 ? $due_date->format('Y-m-d') : $this->generateScheduleByFrequency($frequency_id, $due_date)->format('Y-m-d'),
                'amount' => $amount,
                'is_amount' => true,
            ];
        }

        if ($adjustment != 0) {
            $schedule[$remaining_cycles - 1]['amount'] += $adjustment;
        }

        return $schedule;
    }

    private function generateScheduleByFrequency(int $frequency_id, Carbon $date)
    {

        return match ($frequency_id) {
            RecurringInvoice::FREQUENCY_DAILY => $date->startOfDay()->addDay(),
            RecurringInvoice::FREQUENCY_WEEKLY => $date->startOfDay()->addWeek(),
            RecurringInvoice::FREQUENCY_TWO_WEEKS => $date->startOfDay()->addWeeks(2),
            RecurringInvoice::FREQUENCY_FOUR_WEEKS => $date->startOfDay()->addWeeks(4),
            RecurringInvoice::FREQUENCY_MONTHLY => $date->startOfDay()->addMonthNoOverflow(),
            RecurringInvoice::FREQUENCY_TWO_MONTHS => $date->startOfDay()->addMonthsNoOverflow(2),
            RecurringInvoice::FREQUENCY_THREE_MONTHS => $date->startOfDay()->addMonthsNoOverflow(3),
            RecurringInvoice::FREQUENCY_FOUR_MONTHS => $date->startOfDay()->addMonthsNoOverflow(4),
            RecurringInvoice::FREQUENCY_SIX_MONTHS => $date->startOfDay()->addMonthsNoOverflow(6),
            RecurringInvoice::FREQUENCY_ANNUALLY => $date->startOfDay()->addYear(),
            RecurringInvoice::FREQUENCY_TWO_YEARS => $date->startOfDay()->addYears(2),
            RecurringInvoice::FREQUENCY_THREE_YEARS => $date->startOfDay()->addYears(3),
            default => $date->startOfDay()->addMonthNoOverflow(),
        };
    }
}

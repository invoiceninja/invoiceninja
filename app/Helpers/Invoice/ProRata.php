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

namespace App\Helpers\Invoice;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Carbon;

class ProRata
{
    /**
     * Returns the amount to refund based on
     * the time interval and the frequency duration
     *
     * @param float $amount
     * @param Carbon $from_date
     * @param Carbon $to_date
     * @param int $frequency
     * @return float
     */
    public function refund(float $amount, Carbon $from_date, Carbon $to_date, int $frequency) :float
    {
        $days = $from_date->copy()->diffInDays($to_date);
        $days_in_frequency = $this->getDaysInFrequency($frequency);

        return round((($days / $days_in_frequency) * $amount), 2);
    }

    /**
     * Returns the amount to charge based on
     * the time interval and the frequency duration
     *
     * @param float $amount
     * @param Carbon $from_date
     * @param Carbon $to_date
     * @param int $frequency
     * @return float
     */
    public function charge(float $amount, Carbon $from_date, Carbon $to_date, int $frequency) :float
    {
        $days = $from_date->copy()->diffInDays($to_date);
        $days_in_frequency = $this->getDaysInFrequency($frequency);

        return round((($days / $days_in_frequency) * $amount), 2);
    }

    /**
     * Prepares the line items of an invoice
     * to be pro rata refunded.
     *
     * @param Invoice $invoice
     * @param bool $is_credit
     * @return array
     * @throws Exception
     */
    public function refundItems(Invoice $invoice, $is_credit = false) :array
    {
        if (! $invoice) {
            return [];
        }

        $recurring_invoice = RecurringInvoice::find($invoice->recurring_id)->first();

        if (! $recurring_invoice) {
            throw new \Exception("Invoice isn't attached to a recurring invoice");
        }

        /* depending on whether we are creating an invoice or a credit*/
        $multiplier = $is_credit ? 1 : -1;

        $start_date = Carbon::parse($invoice->date);

        $line_items = [];

        foreach ($invoice->line_items as $item) {
            if ($item->product_key != ctrans('texts.refund')) {
                $item->quantity = 1;
                $item->cost = $this->refund($item->cost * $multiplier, $start_date, now(), $recurring_invoice->frequency_id);
                $item->product_key = ctrans('texts.refund');
                $item->notes = ctrans('texts.refund').': '.$item->notes;

                $line_items[] = $item;
            }
        }

        return $line_items;
    }

    private function getDaysInFrequency($frequency)
    {
        switch ($frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return 1;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return 7;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return 14;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return now()->diffInDays(now()->addWeeks(4));
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return now()->diffInDays(now()->addMonthNoOverflow());
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(2));
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(3));
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(4));
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(6));
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return now()->diffInDays(now()->addYear());
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return now()->diffInDays(now()->addYears(2));
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return now()->diffInDays(now()->addYears(3));
            default:
                return 0;
        }
    }
}

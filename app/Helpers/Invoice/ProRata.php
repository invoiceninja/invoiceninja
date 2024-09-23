<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Invoice;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Exception;
use Illuminate\Support\Carbon;

class ProRata
{
    /**
     * Returns the amount to refund based on
     * the time interval and the frequency duration
     *
     * @param float $amount
     * @param \Illuminate\Support\Carbon | \Carbon\Carbon $from_date
     * @param \Illuminate\Support\Carbon | \Carbon\Carbon $to_date
     * @param int $frequency
     * @return float
     */
    public function refund(float $amount, $from_date, $to_date, int $frequency): float
    {
        $days = intval(abs($from_date->copy()->diffInDays($to_date)));
        $days_in_frequency = $this->getDaysInFrequency($frequency);

        return round((($days / $days_in_frequency) * $amount), 2);
    }

    /**
     * Returns the amount to charge based on
     * the time interval and the frequency duration
     *
     * @param float $amount
     * @param \Illuminate\Support\Carbon | \Carbon\Carbon  $from_date
     * @param \Illuminate\Support\Carbon | \Carbon\Carbon  $to_date
     * @param int $frequency
     * @return float
     */
    public function charge(float $amount, $from_date, $to_date, int $frequency): float
    {
        $days = intval(abs($from_date->copy()->diffInDays($to_date)));
        $days_in_frequency = $this->getDaysInFrequency($frequency);

        return round((($days / $days_in_frequency) * $amount), 2);
    }

    /**
     * Prepares the line items of an invoice
     * to be pro rata refunded.
     *
     * @param ?Invoice $invoice
     * @param bool $is_credit
     * @return array
     * @throws Exception
     */
    public function refundItems(?Invoice $invoice, $is_credit = false): array
    {
        if (! $invoice) {
            return [];
        }

        /** @var \App\Models\RecurringInvoice $recurring_invoice **/
        $recurring_invoice = RecurringInvoice::find($invoice->recurring_id);

        if (! $recurring_invoice) { // @phpstan-ignore-line
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
                return intval(abs(now()->diffInDays(now()->addWeeks(4))));
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return intval(abs(now()->diffInDays(now()->addMonthNoOverflow())));
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(2))));
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(3))));
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(4))));
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(6))));
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return intval(abs(now()->diffInDays(now()->addYear())));
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return intval(abs(now()->diffInDays(now()->addYears(2))));
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return intval(abs(now()->diffInDays(now()->addYears(3))));
            default:
                return 0;
        }
    }
}

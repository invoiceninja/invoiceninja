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

namespace App\Factory;

use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RecurringExpenseToExpenseFactory
{
    public static function create(RecurringExpense $recurring_expense): Expense
    {
        $expense = new Expense();
        $expense->user_id = $recurring_expense->user_id;
        $expense->assigned_user_id = $recurring_expense->assigned_user_id;
        $expense->client_id = $recurring_expense->client_id;
        $expense->vendor_id = $recurring_expense->vendor_id;
        $expense->invoice_id = $recurring_expense->invoice_id;
        $expense->currency_id = $recurring_expense->currency_id;
        $expense->company_id = $recurring_expense->company_id;
        $expense->bank_id = $recurring_expense->bank_id;
        $expense->exchange_rate = $recurring_expense->exchange_rate;
        $expense->is_deleted = false;
        $expense->should_be_invoiced = $recurring_expense->should_be_invoiced;
        $expense->tax_name1 = $recurring_expense->tax_name1;
        $expense->tax_rate1 = $recurring_expense->tax_rate1;
        $expense->tax_name2 = $recurring_expense->tax_name2;
        $expense->tax_rate2 = $recurring_expense->tax_rate2;
        $expense->tax_name3 = $recurring_expense->tax_name3;
        $expense->tax_rate3 = $recurring_expense->tax_rate3;
        $expense->date = now()->format('Y-m-d');
        // $expense->payment_date = $recurring_expense->payment_date ?: now()->format('Y-m-d');
        $expense->amount = $recurring_expense->amount;
        $expense->foreign_amount = $recurring_expense->foreign_amount ?: 0;

        //11-09-2022 - we should be tracking the recurring expense!!
        $expense->recurring_expense_id = $recurring_expense->id;

        $expense->public_notes = self::transformObject($recurring_expense->public_notes, $recurring_expense);
        $expense->private_notes = self::transformObject($recurring_expense->private_notes, $recurring_expense);

        $expense->transaction_reference = $recurring_expense->transaction_reference;
        $expense->custom_value1 = $recurring_expense->custom_value1;
        $expense->custom_value2 = $recurring_expense->custom_value2;
        $expense->custom_value3 = $recurring_expense->custom_value3;
        $expense->custom_value4 = $recurring_expense->custom_value4;
        $expense->transaction_id = null;
        $expense->category_id = $recurring_expense->category_id;
        $expense->payment_type_id = $recurring_expense->payment_type_id;
        $expense->project_id = $recurring_expense->project_id;
        $expense->invoice_documents = $recurring_expense->invoice_documents;
        $expense->tax_amount1 = $recurring_expense->tax_amount1 ?: 0;
        $expense->tax_amount2 = $recurring_expense->tax_amount2 ?: 0;
        $expense->tax_amount3 = $recurring_expense->tax_amount3 ?: 0;
        $expense->uses_inclusive_taxes = $recurring_expense->uses_inclusive_taxes;
        $expense->calculate_tax_by_amount = $recurring_expense->calculate_tax_by_amount;
        $expense->invoice_currency_id = $recurring_expense->invoice_currency_id;

        return $expense;
    }

    public static function transformObject(?string $value, $recurring_expense): ?string
    {
        if (! $value) {
            return '';
        }

        if ($recurring_expense->client) {
            $locale = $recurring_expense->client->locale();
            $date_format = $recurring_expense->client->date_format();
        } else {
            $locale = $recurring_expense->company->locale();

            //@deprecated
            // $date_formats = Cache::get('date_formats');

            /** @var \Illuminate\Support\Collection<\App\Models\DateFormat> */
            $date_formats = app('date_formats');

            $date_format = $date_formats->first(function ($item) use ($recurring_expense) {
                return $item->id == $recurring_expense->company->settings->date_format_id;
            })->format;
        }

        Carbon::setLocale($locale);

        $replacements = [
            'literal' => [
                ':MONTHYEAR' => \sprintf(
                    '%s %s',
                    Carbon::createFromDate(now()->year, now()->month)->translatedFormat('F'),
                    now()->year,
                ),
                ':MONTH' => Carbon::createFromDate(now()->year, now()->month)->translatedFormat('F'),
                ':YEAR' => now()->year,
                ':QUARTER' => 'Q'.now()->quarter,
                ':WEEK_BEFORE' => \sprintf(
                    '%s %s %s',
                    Carbon::now()->subDays(7)->translatedFormat($date_format),
                    ctrans('texts.to'),
                    Carbon::now()->translatedFormat($date_format)
                ),
                ':WEEK_AHEAD' => \sprintf(
                    '%s %s %s',
                    Carbon::now()->addDays(7)->translatedFormat($date_format),
                    ctrans('texts.to'),
                    Carbon::now()->addDays(14)->translatedFormat($date_format)
                ),
                ':WEEK' => \sprintf(
                    '%s %s %s',
                    Carbon::now()->translatedFormat($date_format),
                    ctrans('texts.to'),
                    Carbon::now()->addDays(7)->translatedFormat($date_format)
                ),
            ],
            'raw' => [
                ':MONTH' => now()->month,
                ':YEAR' => now()->year,
                ':QUARTER' => now()->quarter,
            ],
            'ranges' => [
                'MONTHYEAR' => Carbon::createFromDate(now()->year, now()->month),
            ],
            'ranges_raw' => [
                'MONTH' => now()->month,
                'YEAR' => now()->year,
            ],
        ];

        // First case, with ranges.
        preg_match_all('/\[(.*?)]/', $value, $ranges);

        $matches = array_shift($ranges);

        foreach ($matches as $match) {
            if (! Str::contains($match, '|')) {
                continue;
            }

            // if (Str::contains($match, '|')) {
            $parts = explode('|', $match); // [ '[MONTH', 'MONTH+2]' ]

            $left = substr($parts[0], 1); // 'MONTH'
            $right = substr($parts[1], 0, -1); // MONTH+2

            // If left side is not part of replacements, skip.
            if (! array_key_exists($left, $replacements['ranges'])) {
                continue;
            }

            $_left = Carbon::createFromDate(now()->year, now()->month)->translatedFormat('F Y');
            $_right = '';

            // If right side doesn't have any calculations, replace with raw ranges keyword.
            if (! Str::contains($right, ['-', '+', '/', '*'])) {
                $_right = Carbon::createFromDate(now()->year, now()->month)->translatedFormat('F Y');
            }

            // If right side contains one of math operations, calculate.
            if (Str::contains($right, ['+'])) {
                $operation = preg_match_all('/(?!^-)[+*\/-](\s?-)?/', $right, $_matches);

                $_operation = array_shift($_matches)[0]; // + -

                $_value = explode($_operation, $right); // [MONTHYEAR, 4]

                $_right = Carbon::createFromDate(now()->year, now()->month)->addMonths($_value[1])->translatedFormat('F Y'); //@phpstan-ignore-line
            }

            $replacement = sprintf('%s to %s', $_left, $_right);

            $value = preg_replace(
                sprintf('/%s/', preg_quote($match)),
                $replacement,
                $value,
                1
            );
            // }
        }

        // Second case with more common calculations.
        preg_match_all('/:([^:\s]+)/', $value, $common);

        $matches = array_shift($common);

        foreach ($matches as $match) {
            $matches = collect($replacements['literal'])->filter(function ($value, $key) use ($match) {
                return Str::startsWith($match, $key);
            });

            if ($matches->count() === 0) {
                continue;
            }

            if (! Str::contains($match, ['-', '+', '/', '*'])) {
                $value = preg_replace(
                    sprintf('/%s/', $matches->keys()->first()),
                    $replacements['literal'][$matches->keys()->first()],
                    $value,
                    1
                );
            }

            if (Str::contains($match, ['-', '+', '/', '*'])) {
                $operation = preg_match_all('/(?!^-)[+*\/-](\s?-)?/', $match, $_matches);

                $_operation = array_shift($_matches)[0];

                $_value = explode($_operation, $match); // [:MONTH, 4]

                $raw = strtr($matches->keys()->first(), $replacements['raw']); // :MONTH => 1

                $number = $res = preg_replace('/[^0-9]/', '', $_value[1]); // :MONTH+1. || :MONTH+2! => 1 || 2

                $target = "/{$matches->keys()->first()}\\{$_operation}{$number}/"; // /:$KEYWORD\\$OPERATION$VALUE => /:MONTH\\+1

                $output = (int) $raw + (int) $_value[1];

                if ($operation == '+') {
                    $output = (int) $raw + (int) $_value[1]; // 1 (:MONTH) + 4
                }

                if ($_operation == '-') {
                    $output = (int) $raw - (int) $_value[1]; // 1 (:MONTH) - 4
                }

                if ($_operation == '/' && (int) $_value[1] != 0) {
                    $output = (int) $raw / (int) $_value[1]; // 1 (:MONTH) / 4
                }

                if ($_operation == '*') {
                    $output = (int) $raw * (int) $_value[1]; // 1 (:MONTH) * 4
                }

                if ($matches->keys()->first() == ':MONTH') {
                    $output = \Carbon\Carbon::create()->month($output)->translatedFormat('F');
                }

                if ($matches->keys()->first() == ':MONTHYEAR') {

                    $final_date = now()->addMonths($output - now()->month);

                    $output =    \sprintf(
                        '%s %s',
                        $final_date->translatedFormat('F'),
                        $final_date->year,
                    );
                }

                $value = preg_replace(
                    $target,
                    $output,
                    $value,
                    1
                );
            }
        }

        return $value;
    }
}

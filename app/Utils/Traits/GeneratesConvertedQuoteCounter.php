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

namespace App\Utils\Traits;

use App\Models\BaseModel;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Task;
use App\Models\Timezone;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class GeneratesConvertedQuoteCounter.
 *
 * Counter helpers used when converting a quote to an invoice so the invoice
 * can reuse the quote's counter value when patterns allow.
 */
trait GeneratesConvertedQuoteCounter
{
    private int $update_counter;

    /**
     * Builds an invoice number from the counter extracted from the quote number.
     *
     * @param  Quote  $quote
     * @param  Invoice  $invoice
     * @param  Client  $client
     * @return string|false  The formatted invoice number, or false if already taken
     */
    private function harvestQuoteCounter($quote, $invoice, Client $client)
    {
        $settings = $client->getMergedSettings();

        $pattern = $settings->quote_number_pattern;

        if (strlen($pattern) > 1 && (stripos($pattern, 'counter') === false)) {
            $pattern = $pattern . '{$counter}';
        }

        $number = $this->applyNumberPattern($quote, '_stubling_', $pattern);

        $prefix_counter = str_replace('_stubling_', '', $number);
        $counter = str_replace($prefix_counter, '', $quote->number);

        return $this->getNextEntityNumber($invoice, $client, intval($counter));
    }

    /**
     * Formats an invoice number from a harvested counter and checks uniqueness.
     *
     * @param  Invoice  $invoice
     * @param  Client  $client
     * @param  int|string  $counter
     * @return string|false  The formatted number, or false if already taken
     */
    private function getNextEntityNumber($invoice, Client $client, $counter = '')
    {
        $settings = $client->getMergedSettings();

        $pattern = $settings->invoice_number_pattern;

        if (strlen($pattern) > 1 && (stripos($pattern, 'counter') === false)) {
            $pattern = $pattern . '{$counter}';
        }

        $padding = $client->getSetting('counter_padding');

        $number = $this->padCounter($counter, $padding);

        $number = $this->applyNumberPattern($invoice, $number, $pattern);

        $check = Invoice::query()->whereCompanyId($client->company_id)->whereNumber($number)->withTrashed()->exists();

        if ($check) {
            return false;
        }

        return $number;
    }

    /**
     * Returns the client-scoped number pattern for the given entity.
     *
     * @param  string  $entity  Entity class name
     * @param  Client  $client
     * @return string|null
     */
    private function getNumberPattern($entity, Client $client)
    {
        $pattern_string = '';

        switch ($entity) {
            case Invoice::class:
                $pattern_string = 'invoice_number_pattern';
                break;
            case Quote::class:
                $pattern_string = 'quote_number_pattern';
                break;
            case RecurringInvoice::class:
                $pattern_string = 'recurring_invoice_number_pattern';
                break;
            case Payment::class:
                $pattern_string = 'payment_number_pattern';
                break;
            case Credit::class:
                $pattern_string = 'credit_number_pattern';
                break;
            case Project::class:
                $pattern_string = 'project_number_pattern';
                break;
        }

        return $client->getSetting($pattern_string);
    }

    /**
     * Maps an entity class to its settings counter property name.
     *
     * @param  string  $entity  Entity class name
     * @param  Client|null  $client  Used to resolve shared quote/credit counters
     * @return string
     */
    private function getEntityCounter($entity, $client)
    {
        switch ($entity) {
            case Invoice::class:
                return 'invoice_number_counter';
                break;
            case Quote::class:

                if ($this->hasSharedCounter($client, 'quote')) {
                    return 'invoice_number_counter';
                }

                return 'quote_number_counter';
                break;
            case RecurringInvoice::class:
                return 'recurring_invoice_number_counter';
                break;
            case RecurringQuote::class:
                return 'recurring_quote_number_counter';
                break;
            case RecurringExpense::class:
                return 'recurring_expense_number_counter';
                break;
            case Payment::class:
                return 'payment_number_counter';
                break;
            case Credit::class:
                if ($this->hasSharedCounter($client, 'credit')) {
                    return 'invoice_number_counter';
                }

                return 'credit_number_counter';
                break;
            case Project::class:
                return 'project_number_counter';
                break;

            default:
                return 'default_number_counter';
                break;
        }
    }

    /**
     * Gets the next invoice number.
     *
     * @param  Client  $client
     * @param  Invoice|null  $invoice
     * @param  bool  $is_recurring
     * @return string
     */
    public function getNextInvoiceNumber(Client $client, ?Invoice $invoice, $is_recurring = false): string
    {
        $entity_number = $this->getNextEntityNumber(Invoice::class, $client, $is_recurring);

        return $this->replaceUserVars($invoice, $entity_number);
    }

    /**
     * Gets the next credit number.
     *
     * @param  Client  $client
     * @param  Credit|null  $credit
     * @return string
     */
    public function getNextCreditNumber(Client $client, ?Credit $credit): string
    {
        $entity_number = $this->getNextEntityNumber(Credit::class, $client);

        return $this->replaceUserVars($credit, $entity_number);
    }

    /**
     * Gets the next quote number.
     *
     * @param  Client  $client
     * @param  Quote|null  $quote
     * @return string
     */
    public function getNextQuoteNumber(Client $client, ?Quote $quote)
    {
        $entity_number = $this->getNextEntityNumber(Quote::class, $client);

        return $this->replaceUserVars($quote, $entity_number);
    }

    /**
     * Gets the next recurring invoice number.
     *
     * @param  Client  $client
     * @param  RecurringInvoice|null  $recurring_invoice
     * @return string
     */
    public function getNextRecurringInvoiceNumber(Client $client, $recurring_invoice)
    {
        $entity_number = $this->getNextEntityNumber(RecurringInvoice::class, $client);

        return $this->replaceUserVars($recurring_invoice, $entity_number);
    }

    /**
     * Gets the next recurring quote number.
     *
     * @param  Client  $client
     * @param  RecurringQuote|null  $recurring_quote
     * @return string
     */
    public function getNextRecurringQuoteNumber(Client $client, $recurring_quote)
    {
        $entity_number = $this->getNextEntityNumber(RecurringQuote::class, $client);

        return $this->replaceUserVars($recurring_quote, $entity_number);
    }

    /**
     * Gets the next payment number.
     *
     * @param  Client  $client
     * @param  Payment|null  $payment
     * @return string
     */
    public function getNextPaymentNumber(Client $client, ?Payment $payment): string
    {
        $entity_number = $this->getNextEntityNumber(Payment::class, $client);

        return $this->replaceUserVars($payment, $entity_number);
    }

    /**
     * Gets the next client number.
     *
     * @param  Client  $client
     * @return string
     */
    public function getNextClientNumber(Client $client): string
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        $counter = $client->getSetting('client_number_counter');
        $setting_entity = $client->getSettingEntity('client_number_counter');

        $client_number = $this->checkEntityNumber(Client::class, $client, $counter, $client->getSetting('counter_padding'), $client->getSetting('client_number_pattern'));

        $this->incrementCounter($setting_entity, 'client_number_counter');

        $entity_number = $client_number;

        return $this->replaceUserVars($client, $entity_number);
    }

    /**
     * Gets the next vendor number.
     *
     * @param  Vendor  $vendor
     * @return string
     */
    public function getNextVendorNumber(Vendor $vendor): string
    {
        $this->resetCompanyCounters($vendor->company);

        $counter = $vendor->company->settings->vendor_number_counter;
        $setting_entity = $vendor->company->settings->vendor_number_counter;

        $vendor_number = $this->checkEntityNumber(Vendor::class, $vendor, $counter, $vendor->company->settings->counter_padding, $vendor->company->settings->vendor_number_pattern);

        $this->incrementCounter($vendor->company, 'vendor_number_counter');

        $entity_number = $vendor_number;

        return $this->replaceUserVars($vendor, $entity_number);
    }

    /**
     * Gets the next project number.
     *
     * @param  Project  $project
     * @return string
     */
    public function getNextProjectNumber(Project $project): string
    {
        $entity_number = $this->getNextEntityNumber(Project::class, $project->client, false);

        return $this->replaceUserVars($project, $entity_number);
    }

    /**
     * Gets the next task number.
     *
     * @param  Task  $task
     * @return string
     */
    public function getNextTaskNumber(Task $task): string
    {
        $this->resetCompanyCounters($task->company);

        $counter = $task->company->settings->task_number_counter;
        $setting_entity = $task->company->settings->task_number_counter;

        $task_number = $this->checkEntityNumber(Task::class, $task, $counter, $task->company->settings->counter_padding, $task->company->settings->task_number_pattern);

        $this->incrementCounter($task->company, 'task_number_counter');

        $entity_number = $task_number;

        return $this->replaceUserVars($task, $entity_number);
    }

    /**
     * Gets the next expense number.
     *
     * @param  Expense  $expense
     * @return string
     */
    public function getNextExpenseNumber(Expense $expense): string
    {
        $this->resetCompanyCounters($expense->company);

        $counter = $expense->company->settings->expense_number_counter;
        $setting_entity = $expense->company->settings->expense_number_counter;

        $expense_number = $this->checkEntityNumber(Expense::class, $expense, $counter, $expense->company->settings->counter_padding, $expense->company->settings->expense_number_pattern);

        $this->incrementCounter($expense->company, 'expense_number_counter');

        $entity_number = $expense_number;

        return $this->replaceUserVars($expense, $entity_number);
    }

    /**
     * Gets the next recurring expense number.
     *
     * @param  RecurringExpense  $expense
     * @return string
     */
    public function getNextRecurringExpenseNumber(RecurringExpense $expense): string
    {
        $this->resetCompanyCounters($expense->company);

        // - 18/09/21 need to set this property if it doesn't exist. //todo refactor this for other properties
        if (! property_exists($expense->company->settings, 'recurring_expense_number_counter')) {
            $settings = $expense->company->settings;
            $settings->recurring_expense_number_counter = 1;
            $settings->recurring_expense_number_pattern = '';
            $expense->company->settings = $settings;
            $expense->company->save();
        }

        $counter = $expense->company->settings->recurring_expense_number_counter;
        $setting_entity = $expense->company->settings->recurring_expense_number_counter;

        $expense_number = $this->checkEntityNumber(RecurringExpense::class, $expense, $counter, $expense->company->settings->counter_padding, $expense->company->settings->recurring_expense_number_pattern);

        $this->incrementCounter($expense->company, 'recurring_expense_number_counter');

        $entity_number = $expense_number;

        return $this->replaceUserVars($expense, $entity_number);
    }

    /**
     * Whether quotes or credits share the invoice counter.
     *
     * @param  Client  $client
     * @param  string  $type  Either 'quote' or 'credit'
     * @return bool
     */
    public function hasSharedCounter(Client $client, string $type = 'quote'): bool
    {
        if ($type == 'quote') {
            return (bool) $client->getSetting('shared_invoice_quote_counter');
        }

        //if ($type == 'credit') {
        return (bool) $client->getSetting('shared_invoice_credit_counter');
    }

    /**
     * Builds a unique entity number, advancing the counter until unused.
     *
     * @param  string  $class  Entity class name
     * @param  BaseModel  $entity
     * @param  int  $counter
     * @param  int  $padding
     * @param  string  $pattern
     * @param  string  $prefix
     * @return string
     */
    private function checkEntityNumber($class, $entity, $counter, $padding, $pattern, $prefix = '')
    {
        $check = false;
        $check_counter = 1;

        do {
            $number = $this->padCounter($counter, $padding);

            $number = $this->applyNumberPattern($entity, $number, $pattern);

            $number = $this->prefixCounter($number, $prefix);

            $check = $class::whereCompanyId($entity->company_id)->whereNumber($number)->withTrashed()->exists();

            $counter++;
            $check_counter++;

            if ($check_counter > 100) {
                return $number . '_' . Str::random(5);
            }
        } while ($check);

        return $number;
    }

    /**
     * Whether the given number is unused for the entity class within the company.
     *
     * @param  string  $class  Entity class name
     * @param  BaseModel  $entity
     * @param  string  $number
     * @return bool
     */
    public function checkNumberAvailable($class, $entity, $number): bool
    {
        if ($entity = $class::whereCompanyId($entity->company_id)->whereNumber($number)->withTrashed()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Persists an incremented counter value on the company, group, or client settings.
     *
     * @param  \App\Models\Company|Client|\App\Models\GroupSetting  $entity
     * @param  string  $counter_name
     * @return void
     */
    private function incrementCounter($entity, string $counter_name): void
    {
        $settings = $entity->settings;

        if ($counter_name == 'invoice_number_counter' && ! property_exists($entity->settings, 'invoice_number_counter')) {
            $settings->invoice_number_counter = 0;
        }

        if (! property_exists($settings, $counter_name)) {
            $settings->{$counter_name} = 1;
        }

        $settings->{$counter_name} = $settings->{$counter_name} + 1;

        $entity->settings = $settings;

        $entity->save();
    }

    /**
     * Prepends a prefix to the counter string when a prefix is set.
     *
     * @param  string  $counter
     * @param  string  $prefix
     * @return string
     */
    private function prefixCounter($counter, $prefix): string
    {
        if (strlen($prefix) == 0) {
            return $counter;
        }

        return  $prefix . $counter;
    }

    /**
     * Pads a counter with leading zeros.
     *
     * @param  int  $counter
     * @param  int  $padding
     * @return string
     */
    private function padCounter($counter, $padding): string
    {
        return str_pad($counter, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Resets client/company counters when the configured reset date has passed.
     *
     * @param  Client  $client
     * @return bool|void  False when no reset is due; otherwise void after resetting
     */
    private function resetCounters(Client $client)
    {
        $reset_counter_frequency = (int) $client->getSetting('reset_counter_frequency_id');

        if ($reset_counter_frequency == 0) {
            if ($client->getSetting('reset_counter_date')) {
                $settings = $client->company->settings;
                $settings->reset_counter_date = "";
                $client->company->settings = $settings;
                $client->company->save();
            }

            return;
        }


        $timezone = Timezone::find($client->getSetting('timezone_id'));

        $reset_date = Carbon::parse($client->getSetting('reset_counter_date'), $timezone->name);

        if (! $reset_date->lte(now()) || ! $client->getSetting('reset_counter_date')) {
            return false;
        }

        switch ($reset_counter_frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $new_reset_date = $reset_date->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $new_reset_date = $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $new_reset_date = $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $new_reset_date = $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $new_reset_date = $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $new_reset_date = $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $new_reset_date = $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $new_reset_date = $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $new_reset_date = $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $new_reset_date = $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $new_reset_date = $reset_date->addYears(2);
                break;

            default:
                $new_reset_date = $reset_date->addYear();
                break;
        }

        $settings = $client->company->settings;
        $settings->reset_counter_date = $new_reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;

        $client->company->settings = $settings;
        $client->company->save();
    }

    /**
     * Resets company-level counters when the configured reset date has passed.
     *
     * @param  \App\Models\Company  $company
     * @return bool|void  False when no reset is due; otherwise void after resetting
     */
    private function resetCompanyCounters($company)
    {
        $timezone = Timezone::find($company->settings->timezone_id);

        $reset_date = Carbon::parse($company->settings->reset_counter_date, $timezone->name);

        if (! $reset_date->lte(now()) || ! $company->settings->reset_counter_date) {
            return false;
        }

        switch ($company->reset_counter_frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $reset_date->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $reset_date->addYears(2);
                break;
        }

        $settings = $company->settings;
        $settings->reset_counter_date = $reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->vendor_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;

        $company->settings = $settings;
        $company->save();
    }

    /**
     * Replaces pattern placeholders with counter, date, and entity values.
     *
     * @param  BaseModel  $entity
     * @param  string  $counter
     * @param  string|null  $pattern
     * @return string
     */
    private function applyNumberPattern($entity, string $counter, $pattern): string
    {
        if (! $pattern) {
            return $counter;
        }

        $search = ['{$year}'];
        $replace = [Carbon::now($entity->company->timezone()->name)->format('Y')];

        $search[] = '{$counter}';
        $replace[] = $counter;

        $search[] = '{$client_counter}';
        $replace[] = $counter;

        $search[] = '{$clientCounter}';
        $replace[] = $counter;

        $search[] = '{$group_counter}';
        $replace[] = $counter;

        $search[] = '{$year}';
        $replace[] = Carbon::now($entity->company->timezone()->name)->format('Y');

        if (strstr($pattern, '{$user_id}') || strstr($pattern, '{$userId}')) {
            $user_id = $entity->user_id ? $entity->user_id : 0;
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
            $search[] = '{$userId}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
        }

        $matches = [];
        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            /* The following adjusts for the company timezone - may bork tests depending on the time of day the tests are run!!!!!!*/
            $date = Carbon::now($entity->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        if ($entity instanceof Vendor) {
            $search[] = '{$vendor_id_number}';
            $replace[] = $entity->id_number;
        }

        if ($entity instanceof Expense) {
            if ($entity->vendor) {
                $search[] = '{$vendor_id_number}';
                $replace[] = $entity->vendor->id_number;

                $search[] = '{$vendor_number}';
                $replace[] = $entity->vendor->number;

                $search[] = '{$vendor_custom1}';
                $replace[] = $entity->vendor->custom_value1;

                $search[] = '{$vendor_custom2}';
                $replace[] = $entity->vendor->custom_value2;

                $search[] = '{$vendor_custom3}';
                $replace[] = $entity->vendor->custom_value3;

                $search[] = '{$vendor_custom4}';
                $replace[] = $entity->vendor->custom_value4;
            }

            $search[] = '{$expense_id_number}';
            $replace[] = $entity->id_number;
        }

        if ($entity->client || ($entity instanceof Client)) {
            $client = $entity->client ?: $entity;

            $search[] = '{$client_custom1}';
            $replace[] = $client->custom_value1;

            $search[] = '{$clientCustom1}';
            $replace[] = $client->custom_value1;

            $search[] = '{$client_custom2}';
            $replace[] = $client->custom_value2;

            $search[] = '{$clientCustom2}';
            $replace[] = $client->custom_value2;

            $search[] = '{$client_custom3}';
            $replace[] = $client->custom_value3;

            $search[] = '{$client_custom4}';
            $replace[] = $client->custom_value4;

            $search[] = '{$client_number}';
            $replace[] = $client->number;

            $search[] = '{$client_id_number}';
            $replace[] = $client->id_number;

            $search[] = '{$clientIdNumber}';
            $replace[] = $client->id_number;
        }

        return str_replace($search, $replace, $pattern);
    }

    /**
     * Replaces user custom-field placeholders in a number pattern.
     *
     * @param  BaseModel|null  $entity
     * @param  string  $pattern
     * @return string
     */
    private function replaceUserVars($entity, $pattern)
    {
        if (! $entity) {
            return $pattern;
        }

        $search = [];
        $replace = [];

        $search[] = '{$user_custom1}';
        $replace[] = $entity->user->custom_value1;

        $search[] = '{$user_custom2}';
        $replace[] = $entity->user->custom_value2;

        $search[] = '{$user_custom3}';
        $replace[] = $entity->user->custom_value3;

        $search[] = '{$user_custom4}';
        $replace[] = $entity->user->custom_value4;

        return str_replace($search, $replace, $pattern);
    }
}

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
use App\Models\Company;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
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
 * Class GeneratesCounter.
 */
trait GeneratesCounter
{
    private int $update_counter;

    //todo in the form validation, we need to ensure that if a prefix and pattern is set we throw a validation error,
    //only one type is allow else this will cause confusion to the end user
    
    /**
     * Gets the next Peppol credit number using the credit counter pattern.
     *
     * @param  Client  $client
     * @param  Invoice  $invoice  Source invoice used for user-variable replacement
     * @return string
     */
    public function getPeppolCreditNumber(Client $client, Invoice $invoice)
    {
        $entity_number = $this->getNextEntityNumber(Credit::class, $client);

        return $this->replaceUserVars($invoice, $entity_number);
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
        $entity_number = $this->getNextEntityNumber(Client::class, $client);

        return $this->replaceUserVars($client, $entity_number);
    }

    /**
     * Generates the next number for entities counted at the company level.
     *
     * @param  string  $entity  Entity class name (e.g. Vendor::class)
     * @param  BaseModel  $model  The entity being numbered
     * @return string
     */
    private function getNextCompanyEntityNumber(string $entity, $model): string
    {
        $company = $model->company;

        $this->resetCompanyCounters($company);

        $counter_string = $this->getEntityCounter($entity, null);

        $entity_number = $this->checkEntityNumber(
            $entity,
            $model,
            $company->getSetting($counter_string),
            $company->getSetting('counter_padding'),
            $this->getCompanyNumberPattern($entity, $company)
        );

        $this->incrementCounter($company, $counter_string);

        return $this->replaceUserVars($model, $entity_number);
    }

    /**
     * Gets the next vendor number.
     *
     * @param  Vendor  $vendor
     * @return string
     */
    public function getNextVendorNumber(Vendor $vendor): string
    {
        return $this->getNextCompanyEntityNumber(Vendor::class, $vendor);
    }

    /**
     * Gets the next project number (client-scoped when a client is set, otherwise company-scoped).
     *
     * @param  Project  $project
     * @return string
     */
    public function getNextProjectNumber(Project $project): string
    {
        if (! $project->client_id) {
            return $this->getNextCompanyEntityNumber(Project::class, $project);
        }

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
        return $this->getNextCompanyEntityNumber(Task::class, $task);
    }

    /**
     * Gets the next expense number.
     *
     * @param  Expense  $expense
     * @return string
     */
    public function getNextExpenseNumber(Expense $expense): string
    {
        return $this->getNextCompanyEntityNumber(Expense::class, $expense);
    }

    /**
     * Gets the next purchase order number.
     *
     * @param  PurchaseOrder  $purchase_order
     * @return string
     */
    public function getNextPurchaseOrderNumber(PurchaseOrder $purchase_order): string
    {
        return $this->getNextCompanyEntityNumber(PurchaseOrder::class, $purchase_order);
    }

    /**
     * Gets the next recurring expense number.
     *
     * @param  RecurringExpense  $expense
     * @return string
     */
    public function getNextRecurringExpenseNumber(RecurringExpense $expense): string
    {
        return $this->getNextCompanyEntityNumber(RecurringExpense::class, $expense);
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

        //credit
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
    private function checkEntityNumber($class, $entity, $counter, $padding, $pattern, $prefix = ''): string
    {
        $check = false;
        $check_counter = 1;

        do {
            $number = $this->getFormattedEntityNumber($entity, $counter, $padding, $pattern, $prefix);

            $check = $class::where('company_id', $entity->company_id)->where('number', $number)->withTrashed()->exists();

            $counter++;
            $check_counter++;

            if ($check_counter > 100) {
                $this->update_counter = $counter--;

                return $number . '_' . Str::random(5);
            }
        } while ($check);

        $this->update_counter = $counter--;

        return $number;
    }

    /**
     * Formats an entity number with padding, pattern, prefix, and user variables.
     *
     * @param  BaseModel  $entity
     * @param  int  $counter
     * @param  int  $padding
     * @param  string  $pattern
     * @param  string  $prefix
     * @return string
     */
    public function getFormattedEntityNumber($entity, $counter, $padding, $pattern, $prefix = ''): string
    {
        $number = $this->padCounter($counter, $padding);

        $number = $this->applyNumberPattern($entity, $number, $pattern);

        $number = $this->prefixCounter($number, $prefix);

        $number = $this->replaceUserVars($entity, $number);

        if ($number === '') {
            $number = $this->padCounter($counter, $padding);
        }

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
     * Persists the updated counter value on the company, group, or client settings.
     *
     * @param  Company|Client|\App\Models\GroupSetting  $entity
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

        $settings->{$counter_name} = $this->update_counter;

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
        $settings_entity = $client->getSettingEntity('reset_counter_frequency_id');
        $settings = $settings_entity->settings;

        if ($reset_counter_frequency == 0) {

            if ($client->getSetting('reset_counter_date')) {
                $settings->reset_counter_date = "";
                $settings_entity->settings = $settings;
                $settings_entity->saveQuietly();
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

        $settings->reset_counter_date = $new_reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

        $settings_entity->settings = $settings;
        $settings_entity->saveQuietly();
    }

    /**
     * Resets company-level counters when the configured reset date has passed.
     *
     * @param  Company  $company
     * @return bool|void  False when no reset is due; otherwise void after resetting
     */
    private function resetCompanyCounters($company)
    {
        $timezone = Timezone::find($company->settings->timezone_id);

        $reset_date = Carbon::parse($company->settings->reset_counter_date, $timezone->name);

        if (! $reset_date->lte(now()) || ! $company->settings->reset_counter_date) {
            return false;
        }

        $settings = $company->settings;

        $reset_counter_frequency = (int) $settings->reset_counter_frequency_id;

        if ($reset_counter_frequency == 0) {
            if ($settings->reset_counter_date) {
                $settings->reset_counter_date = "";
                $company->settings = $settings;
                $company->save();
            }

            return;
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

        $settings->reset_counter_date = $new_reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

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

        $search = [];
        $replace = [];

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
            $replace[] = $client->id_number ?: $client->number;

            $search[] = '{$clientIdNumber}';
            $replace[] = $client->id_number ?: $client->number;
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
        $replace[] = $entity->user->custom_value1 ?? '';

        $search[] = '{$user_custom2}';
        $replace[] = $entity->user->custom_value2 ?? '';

        $search[] = '{$user_custom3}';
        $replace[] = $entity->user->custom_value3 ?? '';

        $search[] = '{$user_custom4}';
        $replace[] = $entity->user->custom_value4 ?? '';

        return str_replace($search, $replace, $pattern);
    }


    /**
     * Resolves, formats, and increments the next number for a client-scoped entity.
     *
     * @param  string  $entity  Entity class name (e.g. Invoice::class)
     * @param  Client  $client
     * @param  bool  $is_recurring  Whether to apply the recurring number prefix
     * @return string
     */
    private function getNextEntityNumber($entity, Client $client, $is_recurring = false)
    {
        $prefix = '';

        $this->resetCounters($client);

        $counter_string = $this->getEntityCounter($entity, $client);

        $pattern = $this->getNumberPattern($entity, $client);

        if ((strpos($pattern, 'clientCounter') !== false) || (strpos($pattern, 'client_counter') !== false)) {
            if (property_exists($client->settings, $counter_string)) {
                $counter = $client->settings->{$counter_string};
            } else {
                $counter = 1;
            }

            $counter_entity = $client;
        } elseif ((strpos($pattern, 'groupCounter') !== false) || (strpos($pattern, 'group_counter') !== false)) {
            if ($client->group_settings()->exists() && property_exists($client->group_settings?->settings, $counter_string)) {
                $counter = $client->group_settings?->settings?->{$counter_string};
            } else {
                $counter = 1;
            }

            $counter_entity = $client->group_settings ?: $client->company;
        } else {
            $counter = $client->company->settings->{$counter_string};
            $counter_entity = $client->company;
        }

        //If it is a quote - we need to
        $pattern = $this->getNumberPattern($entity, $client);

        if (strlen($pattern) > 1 && (stripos($pattern, 'counter') === false)) {
            $pattern = $pattern . '{$counter}';
        }

        $padding = $client->getSetting('counter_padding');

        if ($is_recurring) {
            $prefix = $client->getSetting('recurring_number_prefix');
        }

        $entity_number = $this->checkEntityNumber($entity, $client, $counter, $padding, $pattern, $prefix);

        $this->incrementCounter($counter_entity, $counter_string);

        return $entity_number;
    }

    /**
     * Returns the company-level number pattern for the given entity, appending {$counter} if missing.
     *
     * @param  string  $entity  Entity class name
     * @param  Company  $company
     * @return string
     */
    private function getCompanyNumberPattern(string $entity, Company $company): string
    {
        $pattern_string = null;

        $pattern_string = match ($entity) {
            Vendor::class => 'vendor_number_pattern',
            Task::class => 'task_number_pattern',
            Project::class => 'project_number_pattern',
            PurchaseOrder::class => 'purchase_order_number_pattern',
            RecurringExpense::class => 'recurring_expense_number_pattern',
            Expense::class => 'expense_number_pattern',
            default => null,
        };

        $pattern = $pattern_string ? $company->getSetting($pattern_string) : '';

        if (stripos($pattern, 'counter') === false) {
            $pattern .= '{$counter}';
        }

        return $pattern;
    }

    /**
     * Returns the client-scoped number pattern for the given entity, appending {$counter} if missing.
     *
     * @param  string  $entity  Entity class name
     * @param  Client  $client
     * @return string
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
            case Client::class:
                $pattern_string = 'client_number_pattern';
                break;
        }

        $pattern = $client->getSetting($pattern_string);

        if (stripos($pattern ?? '', 'counter') === false) {
            $pattern .= '{$counter}';
        }

        return $pattern;
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

            case Quote::class:

                if ($this->hasSharedCounter($client, 'quote')) {
                    return 'invoice_number_counter';
                }

                return 'quote_number_counter';

            case RecurringInvoice::class:
                return 'recurring_invoice_number_counter';

            case RecurringQuote::class:
                return 'recurring_quote_number_counter';

            case RecurringExpense::class:
                return 'recurring_expense_number_counter';

            case Payment::class:
                return 'payment_number_counter';

            case Credit::class:
                if ($this->hasSharedCounter($client, 'credit')) {
                    return 'invoice_number_counter';
                }

                return 'credit_number_counter';

            case Client::class:
                return 'client_number_counter';

            case Vendor::class:
                return 'vendor_number_counter';

            case Task::class:
                return 'task_number_counter';

            case Expense::class:
                return 'expense_number_counter';

            case Project::class:
                return 'project_number_counter';

            case PurchaseOrder::class:
                return 'purchase_order_number_counter';

            default:
                return 'default_number_counter';
        }
    }

}

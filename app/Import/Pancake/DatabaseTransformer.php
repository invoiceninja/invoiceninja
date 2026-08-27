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

namespace App\Import\Pancake;

use App\Import\Harvest\AddressParser;
use App\Models\PaymentType;
use App\Models\RecurringInvoice;
use Carbon\CarbonImmutable;

class DatabaseTransformer
{
    public function __construct(
        private readonly AddressParser $address_parser,
        private string $timezone = 'UTC',
    ) {}

    public function useTimezone(string $timezone): self
    {
        $this->timezone = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';

        return $this;
    }

    /** @return array<string, mixed> */
    public function company(object $identity): array
    {
        $address = $this->address_parser->parse((string) $identity->mailing_address);

        return $this->record(
            DatabaseEntity::Company,
            $identity->id,
            (string) ($identity->brand_name ?: $identity->site_name ?: "Pancake business {$identity->id}"),
            $this->compact([
                'name' => (string) ($identity->brand_name ?: $identity->site_name),
                'address1' => $address['address1'] ?? null,
                'address2' => $address['address2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'email' => (string) ($identity->billing_email ?: $identity->notify_email),
                'email_from_name' => (string) ($identity->admin_name ?: $identity->brand_name),
                'invoice_terms' => (string) $identity->default_invoice_notes,
                'invoice_footer' => (string) $identity->pdf_footer_contents,
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function taxRate(object $tax): array
    {
        return $this->record(DatabaseEntity::TaxRates, $tax->id, (string) $tax->name, [
            'name' => (string) ($tax->name ?: "Pancake Tax {$tax->id}"),
            'rate' => $this->number($tax->value),
        ]);
    }

    /**
     * @param array<int, object> $meta
     * @param array<int, object> $client_taxes
     * @param array<int, object> $taxes
     * @return array<string, mixed>
     */
    public function client(
        object $client,
        array $meta,
        array $client_taxes,
        array $taxes,
        ?string $currency_code,
        string|int|null $source_id = null,
        bool $append_currency_to_name = false,
    ): array {
        $name = trim((string) $client->company)
            ?: trim((string) $client->first_name . ' ' . (string) $client->last_name)
            ?: trim((string) $client->email)
            ?: "Pancake Client {$client->id}";

        if ($append_currency_to_name && $currency_code) {
            $name .= " [{$currency_code}]";
        }

        $tax_registration_notes = array_values(array_filter(array_map(function (object $client_tax) use ($taxes): ?string {
            $registration = trim((string) $client_tax->tax_registration_id);

            if ($registration === '') {
                return null;
            }

            $tax = $taxes[(int) $client_tax->tax_id] ?? null;
            $name = $tax ? (string) $tax->name : "Tax {$client_tax->tax_id}";

            return trim("{$name}: {$registration}");
        }, $client_taxes)));
        $private_notes = array_filter([
            trim((string) $client->profile),
            $this->metadataNotes($meta),
            $tax_registration_notes !== []
                ? "Pancake tax registrations:\n" . implode("\n", $tax_registration_notes)
                : '',
            "Pancake source: clients:{$client->id}",
            $source_id !== null && (string) $source_id !== (string) $client->id
                ? "Pancake currency-specific client: {$currency_code}"
                : '',
        ]);
        $payload = array_merge(
            [
                'name' => $name,
                'number' => $source_id !== null && (string) $source_id !== (string) $client->id
                    ? 'PAN-C' . $client->id . '-' . $currency_code
                    : "PAN-C{$client->id}",
            ],
            $this->address_parser->parse((string) $client->address),
            $this->compact([
                'phone' => (string) ($client->phone ?: $client->mobile),
                'website' => (string) $client->website,
                'private_notes' => implode("\n\n", $private_notes),
                'language_code' => $this->languageCode((string) $client->language),
                'currency_code' => $currency_code,
            ]),
        );

        $default_tax = collect($client_taxes)->firstWhere('is_default', 1) ?? ($client_taxes[0] ?? null);

        if ($default_tax && trim((string) $default_tax->tax_registration_id) !== '') {
            $payload['vat_number'] = trim((string) $default_tax->tax_registration_id);
        }

        $contacts = [];

        foreach ($this->emails((string) $client->email) ?: [''] as $email) {
            $contacts[] = $this->compact([
                'first_name' => (string) $client->first_name,
                'last_name' => (string) $client->last_name,
                'email' => $email,
                'phone' => (string) ($client->mobile ?: $client->phone),
                'custom_value1' => (string) $client->title,
                'custom_value2' => (string) $client->fax,
            ]);
        }

        $payload['contacts'] = array_values(array_filter(
            $contacts,
            fn(array $contact): bool => $contact !== [],
        ));

        return $this->record(DatabaseEntity::Clients, $source_id ?? $client->id, $name, $payload);
    }

    /** @param array<int, object> $taxes @return array<string, mixed> */
    public function product(object $item, array $taxes): array
    {
        $payload = [
            'product_key' => (string) ($item->name ?: "Pancake Item {$item->id}"),
            'notes' => trim((string) $item->description . "\n\nPancake source: items:{$item->id}"),
            'price' => $this->number($item->rate),
            'quantity' => $this->number($item->qty),
        ];

        $this->applyTaxes($payload, $taxes);

        return $this->record(DatabaseEntity::Products, $item->id, $payload['product_key'], $payload);
    }

    /** @return array<string, mixed> */
    public function vendor(object $supplier): array
    {
        $name = (string) ($supplier->name ?: "Pancake Supplier {$supplier->id}");

        return $this->record(DatabaseEntity::Vendors, $supplier->id, $name, [
            'name' => $name,
            'number' => "PAN-V{$supplier->id}",
            'private_notes' => trim(implode("\n\n", array_filter([
                (string) $supplier->description,
                (string) $supplier->notes,
                "Pancake source: project_expenses_suppliers:{$supplier->id}",
            ]))),
            'contacts' => [],
        ]);
    }

    /** @return array<string, mixed> */
    public function expenseCategory(object $category, ?string $parent_name): array
    {
        $name = (string) ($category->name ?: "Pancake Expense Category {$category->id}");

        if ($parent_name) {
            $name = "{$parent_name} / {$name}";
        }

        return $this->record(DatabaseEntity::ExpenseCategories, $category->id, $name, ['name' => $name]);
    }

    /** @return array<string, mixed> */
    public function taskStatus(object $status): array
    {
        return $this->record(DatabaseEntity::TaskStatuses, $status->id, (string) $status->title, [
            'name' => (string) ($status->title ?: "Pancake Task Status {$status->id}"),
            'color' => (string) $status->background_color,
            'status_order' => (int) $status->id,
        ]);
    }

    /** @return array<string, mixed> */
    public function project(object $project, string|int|null $client_source_id = null): array
    {
        $payload = $this->compact([
            'name' => (string) ($project->name ?: "Pancake Project {$project->id}"),
            'number' => "PAN-P{$project->id}",
            'task_rate' => $this->number($project->rate),
            'due_date' => $this->date($project->due_date),
            'public_notes' => (string) $project->description,
            'private_notes' => trim(implode("\n", array_filter([
                "Pancake source: projects:{$project->id}",
                (int) $project->completed === 1 ? 'Pancake project completed: Yes' : '',
                (int) $project->is_archived === 1 ? 'Pancake project archived: Yes' : '',
                (int) $project->is_flat_rate === 1 ? 'Pancake flat-rate project: Yes' : '',
            ]))),
            'budgeted_hours' => $this->number($project->projected_hours),
            'budgeted_amount' => $this->number($project->projected_hours) * $this->number($project->rate),
        ]);

        return $this->record(
            DatabaseEntity::Projects,
            $project->id,
            $payload['name'],
            $payload,
            [$this->reference('client_id', DatabaseEntity::Clients, $client_source_id ?? $project->client_id)],
        );
    }

    /** @param array<int, object> $times @return array<string, mixed> */
    public function task(object $task, array $times): array
    {
        $time_log = [];

        foreach ($times as $time) {
            [$start, $end] = $this->timeRange($time);

            if ($start <= 0 || $end <= $start) {
                continue;
            }

            $time_log[] = [$start, $end, (string) $time->note, true];
        }

        usort($time_log, fn(array $left, array $right): int => $left[0] <=> $right[0]);

        if ($time_log === [] && $this->number($task->hours) > 0) {
            $start = $this->timestamp($task->date_entered ?: $task->due_date);

            if ($start > 0) {
                $time_log[] = [
                    $start,
                    $start + (int) round($this->number($task->hours) * 3600),
                    'Pancake aggregate task hours',
                    true,
                ];
            }
        }

        $payload = $this->compact([
            'number' => "PAN-T{$task->id}",
            'description' => trim(implode("\n\n", array_filter([
                (string) $task->name,
                (string) $task->notes,
                "Pancake source: project_tasks:{$task->id}",
                (int) $task->completed === 1 ? 'Pancake task completed: Yes' : '',
                (int) $task->is_flat_rate === 1 ? 'Pancake flat-rate task: Yes' : '',
            ]))),
            'rate' => $this->number($task->rate),
            'time_log' => $time_log,
            'custom_value1' => $this->date($task->due_date),
            'custom_value2' => $this->number($task->projected_hours),
        ]);
        $references = [$this->reference('project_id', DatabaseEntity::Projects, $task->project_id)];

        if ((int) $task->status_id > 0) {
            $references[] = $this->reference('status_id', DatabaseEntity::TaskStatuses, $task->status_id);
        }

        return $this->record(DatabaseEntity::Tasks, $task->id, (string) $task->name, $payload, $references);
    }

    /**
     * Invoice Ninja rejects overlapping time logs on one task. Preserve every Pancake time
     * row by distributing overlaps over deterministic continuation tasks.
     *
     * @param array<int, object> $times
     * @return array<int, array<string, mixed>>
     */
    public function tasks(object $task, array $times): array
    {
        $lanes = [];

        usort($times, function (object $left, object $right): int {
            return $this->timeRange($left)[0] <=> $this->timeRange($right)[0];
        });

        foreach ($times as $time) {
            [$start, $end] = $this->timeRange($time);

            if ($start <= 0 || $end <= $start) {
                $lanes[0][] = $time;
                continue;
            }

            $assigned = false;

            foreach ($lanes as &$lane) {
                $last = $lane[array_key_last($lane)];
                [, $last_end] = $this->timeRange($last);

                if ($last_end <= $start) {
                    $lane[] = $time;
                    $assigned = true;
                    break;
                }
            }
            unset($lane);

            if (! $assigned) {
                $lanes[] = [$time];
            }
        }

        if ($lanes === []) {
            $lanes = [[]];
        }

        return array_map(function (array $lane, int $index) use ($task): array {
            $record = $this->task($task, $lane);

            if ($index > 0) {
                $record['source_id'] = "{$task->id}:overlap:{$index}";
                $record['label'] = "{$task->name} (overlap {$index})";
                $record['payload']['number'] = "PAN-T{$task->id}-" . ($index + 1);
                $record['payload']['description'] = "Pancake overlapping time-log continuation {$index}\n\n"
                    . $record['payload']['description'];
            }

            return $record;
        }, $lanes, array_keys($lanes));
    }

    /** @return array<string, mixed> */
    public function standaloneTime(object $time, object $project): array
    {
        $task = (object) [
            'id' => "time:{$time->id}",
            'project_id' => $time->project_id,
            'name' => trim((string) $time->note) ?: "Pancake time entry {$time->id}",
            'notes' => "Standalone Pancake project time row {$time->id}",
            'rate' => $project->rate,
            'hours' => 0,
            'date_entered' => $time->date,
            'due_date' => 0,
            'completed' => 1,
            'is_flat_rate' => 0,
            'projected_hours' => 0,
            'status_id' => 0,
        ];
        $record = $this->task($task, [$time]);
        $record['payload']['number'] = "PAN-TIME{$time->id}";

        return $record;
    }

    public function documentEntity(object $invoice): DatabaseEntity
    {
        $type = strtoupper((string) $invoice->type);

        if ($type === 'ESTIMATE') {
            return DatabaseEntity::Quotes;
        }

        if ($type === 'CREDIT_NOTE') {
            return DatabaseEntity::Credits;
        }

        if ((int) $invoice->is_recurring === 1 && (int) $invoice->recur_id === 0) {
            return DatabaseEntity::RecurringInvoices;
        }

        return DatabaseEntity::Invoices;
    }

    /**
     * @param array<int, object> $rows
     * @param array<int, array<int, object>> $row_taxes
     * @param array<int, object> $partials
     * @return array<string, mixed>
     */
    public function document(
        object $invoice,
        array $rows,
        array $row_taxes,
        array $partials = [],
        string|int|null $client_source_id = null,
    ): array {
        $entity = $this->documentEntity($invoice);
        $date = $this->date($invoice->date_entered);
        $due_date = $this->date($invoice->due_date);
        $line_items = $rows === [] ? [[
            'product_key' => (string) ($invoice->item_name ?: 'Pancake Invoice'),
            'notes' => (string) $invoice->description,
            'quantity' => 1,
            'cost' => $this->number($invoice->amount),
            'type_id' => '1',
        ]] : $this->lineItems($rows, $row_taxes);
        $document_amount = $this->lineItemsAmount($line_items);
        $payload = $this->compact([
            'number' => (string) ($invoice->invoice_number ?: "PAN-{$invoice->id}"),
            'date' => $date,
            'due_date' => $entity === DatabaseEntity::RecurringInvoices ? null : $due_date,
            'exchange_rate' => $this->number($invoice->exchange_rate) ?: 1,
            'public_notes' => (string) $invoice->notes,
            'private_notes' => trim(implode("\n\n", array_filter([
                (string) $invoice->description,
                "Pancake source: invoices:{$invoice->id}",
                trim((string) $invoice->status) !== '' ? "Pancake status: {$invoice->status}" : '',
                (int) $invoice->is_paid === 1 ? 'Pancake marked paid: Yes' : '',
                (int) $invoice->is_archived === 1 ? 'Pancake archived: Yes' : '',
                $this->paymentScheduleNotes($partials),
            ]))),
            'uses_inclusive_taxes' => false,
            'line_items' => $line_items,
        ]);

        if ($entity === DatabaseEntity::RecurringInvoices) {
            $frequency = $this->frequencyId((string) $invoice->frequency);
            $payload['frequency_id'] = $frequency;
            $next_recur_date = $this->date($invoice->next_recur_date);
            $payload['next_send_date'] = $this->nextSendDate($next_recur_date ?: $date, $frequency);
            $payload['remaining_cycles'] = RecurringInvoice::RECURS_INDEFINITELY;
            $payload['due_date_days'] = $this->dueDateDays($date, $due_date);
            $payload['auto_bill'] = (int) $invoice->auto_charge === 1 ? 'always' : 'off';
        } elseif ($entity === DatabaseEntity::Invoices) {
            $unpaid_partial = collect($partials)->first(fn(object $partial): bool => (int) $partial->is_paid !== 1);

            if ($unpaid_partial) {
                $partial = (int) $unpaid_partial->is_percentage === 1
                    ? $document_amount * ($this->number($unpaid_partial->amount) / 100)
                    : $this->number($unpaid_partial->amount);
                $partial_due_date = $this->date($unpaid_partial->due_date);

                if ($partial > 0 && $this->isValidPartialDateRange($date, $partial_due_date, $due_date)) {
                    $payload['partial'] = $partial;
                    $payload['partial_due_date'] = $partial_due_date;
                }
            }
        }

        $references = [$this->reference(
            'client_id',
            DatabaseEntity::Clients,
            $client_source_id ?? $invoice->client_id,
        )];

        if ((int) $invoice->project_id > 0) {
            $references[] = $this->reference('project_id', DatabaseEntity::Projects, $invoice->project_id, false);
        }

        return $this->record(
            $entity,
            $invoice->id,
            $payload['number'],
            $payload,
            $references,
            ['mark_sent' => $this->documentWasSent($invoice) ? 'true' : 'false'],
        );
    }

    /** @param array<int, object> $sections @return array<string, mixed> */
    public function proposal(object $proposal, array $sections, string|int|null $client_source_id = null): array
    {
        $contents = array_map(function (object $section): string {
            return trim(implode("\n", array_filter([
                (string) $section->title,
                (string) $section->subtitle,
                (string) $section->contents,
            ])));
        }, $sections);
        $number = "PAN-PROP-{$proposal->id}";
        $payload = [
            'number' => $number,
            'date' => $this->date($proposal->created),
            'public_notes' => implode("\n\n", array_filter($contents)),
            'private_notes' => trim(implode("\n", array_filter([
                (string) $proposal->title,
                "Pancake source: proposals:{$proposal->id}",
                trim((string) $proposal->proposal_number) !== ''
                    ? "Pancake proposal number: {$proposal->proposal_number}"
                    : '',
                "Pancake status: {$proposal->status}",
                (int) $proposal->is_archived === 1 ? 'Pancake archived: Yes' : '',
            ]))),
            'line_items' => [[
                'product_key' => (string) ($proposal->title ?: 'Proposal'),
                'notes' => 'Pancake proposal content is preserved in the quote notes.',
                'quantity' => 1,
                'cost' => 0,
                'type_id' => '1',
            ]],
        ];
        $references = [$this->reference(
            'client_id',
            DatabaseEntity::Clients,
            $client_source_id ?? $proposal->client_id,
        )];

        if ((int) $proposal->project_id > 0) {
            $references[] = $this->reference('project_id', DatabaseEntity::Projects, $proposal->project_id, false);
        }

        return $this->record(
            DatabaseEntity::Quotes,
            "proposal:{$proposal->id}",
            $number,
            $payload,
            $references,
            ['mark_sent' => (int) $proposal->last_sent > 0 ? 'true' : 'false'],
        );
    }

    /** @return array<string, mixed> */
    public function payment(
        object $payment,
        object $invoice,
        string|int $source_id,
        string|int|null $client_source_id = null,
    ): array {
        $percentage = isset($payment->is_percentage)
            && (int) $payment->is_percentage === 1
            && $this->number($payment->payment_gross) <= 0
                ? $this->number($payment->amount)
                : null;
        $amount = $this->number($payment->payment_gross)
            ?: ($percentage !== null
                ? $this->number($invoice->amount) * ($percentage / 100)
                : $this->number($payment->amount));
        $method = (string) ($payment->payment_method ?? $payment->payment_type ?? '');
        $transaction = (string) ($payment->txn_id ?? '');
        $payload = $this->compact([
            'amount' => $amount,
            'date' => $this->date($payment->payment_date ?: $invoice->payment_date ?: $invoice->date_entered),
            'type_id' => $this->paymentTypeId($method),
            'transaction_reference' => $transaction ?: $method,
            'private_notes' => trim(implode("\n", array_filter([
                "Pancake source: partial_payments:{$source_id}",
                $method !== '' ? "Pancake payment method: {$method}" : '',
                isset($payment->payment_status) && trim((string) $payment->payment_status) !== ''
                    ? "Pancake payment status: {$payment->payment_status}"
                    : '',
                isset($payment->transaction_fee) && $this->number($payment->transaction_fee) !== 0.0
                    ? "Pancake transaction fee: {$payment->transaction_fee}"
                    : '',
                isset($payment->notes) ? (string) $payment->notes : '',
            ]))),
            'invoices' => [['amount' => $amount]],
            'idempotency_key' => 'pancake-db-' . substr(hash('sha256', (string) $source_id), 0, 52),
        ]);

        $record = $this->record(
            DatabaseEntity::Payments,
            $source_id,
            trim("{$invoice->invoice_number} {$payload['date']} {$amount}"),
            $payload,
            [
                $this->reference(
                    'client_id',
                    DatabaseEntity::Clients,
                    $client_source_id ?? $invoice->client_id,
                ),
                $this->reference('invoices.0.invoice_id', DatabaseEntity::Invoices, $invoice->id),
            ],
            ['email_receipt' => 'false'],
        );

        if ($percentage !== null) {
            $record['payment_percentage'] = $percentage;
        }

        return $record;
    }

    /** @param array<int, object> $taxes @return array<string, mixed> */
    public function expense(
        object $expense,
        object $project,
        array $taxes,
        string|int|null $client_source_id = null,
    ): array {
        $payload = $this->compact([
            'number' => "PAN-E{$expense->id}",
            'amount' => $this->number($expense->qty) * $this->number($expense->rate),
            'date' => $this->date($expense->due_date),
            'public_notes' => (string) $expense->name,
            'private_notes' => trim(implode("\n\n", array_filter([
                (string) $expense->description,
                (string) $expense->payment_details,
                "Pancake source: project_expenses:{$expense->id}",
            ]))),
            'transaction_reference' => (string) $expense->invoice_number,
            'should_be_invoiced' => (int) $expense->invoice_item_id === 0,
        ]);

        $this->applyTaxes($payload, $taxes);
        $references = [
            $this->reference('project_id', DatabaseEntity::Projects, $expense->project_id),
            $this->reference(
                'client_id',
                DatabaseEntity::Clients,
                $client_source_id ?? $project->client_id,
            ),
        ];

        if ((int) $expense->supplier_id > 0) {
            $references[] = $this->reference('vendor_id', DatabaseEntity::Vendors, $expense->supplier_id, false);
        }

        if ((int) $expense->category_id > 0) {
            $references[] = $this->reference('category_id', DatabaseEntity::ExpenseCategories, $expense->category_id, false);
        }

        return $this->record(DatabaseEntity::Expenses, $expense->id, (string) $expense->name, $payload, $references);
    }

    /** @return array<string, mixed> */
    public function note(
        string $source_id,
        string $notes,
        DatabaseEntity $parent_entity,
        string|int $parent_source_id,
        string $label,
    ): array {
        return $this->record(
            DatabaseEntity::Notes,
            $source_id,
            $label,
            [
                'entity' => $parent_entity->endpoint(),
                'notes' => $notes,
            ],
            [$this->reference('entity_id', $parent_entity, $parent_source_id)],
        );
    }

    /** @return array<string, mixed> */
    public function documentUpload(
        string $source_id,
        DatabaseEntity $parent_entity,
        string|int $parent_source_id,
        string $path,
        string $filename,
        bool $is_public,
    ): array {
        $filename = basename(str_replace('\\', '/', $filename)) ?: basename($path);

        return $this->record(
            DatabaseEntity::Documents,
            $source_id,
            $filename,
            [],
            [$this->reference('destination_id', $parent_entity, $parent_source_id)],
            [],
            compact('path', 'filename', 'is_public', 'parent_entity'),
        );
    }

    /**
     * @param array<int, object> $rows
     * @param array<int, array<int, object>> $row_taxes
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(array $rows, array $row_taxes): array
    {
        $line_items = [];
        $fixed_discount_total = array_sum(array_map(
            fn(object $row): float => strtolower((string) $row->type) === 'fixed_discount'
                ? abs($this->number($row->discount))
                : 0.0,
            $rows,
        ));
        $discountable_subtotal = max(0.0, array_sum(array_map(
            fn(object $row): float => in_array(strtolower((string) $row->type), ['fixed_discount', 'percentage_discount'], true)
                ? 0.0
                : $this->rowNetAmount($row),
            $rows,
        )) - $fixed_discount_total);

        foreach ($rows as $row) {
            $type = strtolower((string) $row->type);
            $quantity = $this->nullableNumber($row->qty) ?? 1.0;
            $cost = $this->nullableNumber($row->rate);

            if ($cost === null) {
                $row_total = $this->nullableNumber($row->total);
                $cost = $row_total !== null && $quantity != 0.0 ? $row_total / $quantity : 0.0;
            }

            $discount = $this->number($row->discount);
            $is_amount_discount = (int) $row->discount_is_percentage !== 1;

            if ($type === 'fixed_discount') {
                $quantity = 1;
                $cost = -abs($discount);
                $discount = 0;
                $is_amount_discount = false;
            } elseif ($type === 'percentage_discount') {
                $quantity = 1;
                $cost = -abs($discountable_subtotal * ($discount / 100));
                $discountable_subtotal = max(0.0, $discountable_subtotal + $cost);
                $discount = 0;
                $is_amount_discount = false;
            }

            $line = $this->compact([
                'product_key' => (string) ($row->name ?: "Pancake Item {$row->id}"),
                'notes' => (string) $row->description,
                'quantity' => $quantity,
                'cost' => $cost,
                'discount' => $discount,
                'is_amount_discount' => $is_amount_discount,
                'type_id' => $this->isTaskLine($row) ? '2' : '1',
            ]);
            $this->applyTaxes($line, $row_taxes[(int) $row->id] ?? []);
            $line_items[] = $line;
        }

        return $line_items ?: [[
            'product_key' => 'Pancake Invoice',
            'quantity' => 1,
            'cost' => 0,
            'type_id' => '1',
        ]];
    }

    private function rowNetAmount(object $row): float
    {
        $quantity = $this->nullableNumber($row->qty) ?? 1.0;
        $cost = $this->nullableNumber($row->rate);

        if ($cost === null) {
            $row_total = $this->nullableNumber($row->total);
            $cost = $row_total !== null && $quantity != 0.0 ? $row_total / $quantity : 0.0;
        }

        $gross = $quantity * $cost;
        $discount = $this->number($row->discount);

        if ($discount <= 0) {
            return $gross;
        }

        return $gross - ((int) $row->discount_is_percentage === 1
            ? $gross * ($discount / 100)
            : $discount);
    }

    /** @param array<int, array<string, mixed>> $line_items */
    private function lineItemsAmount(array $line_items): float
    {
        $subtotal = 0.0;
        $taxes = [];

        foreach ($line_items as $line_item) {
            $gross = $this->number($line_item['quantity'] ?? 0) * $this->number($line_item['cost'] ?? 0);
            $discount = $this->number($line_item['discount'] ?? 0);
            $line_total = round($gross - ($discount > 0
                ? ((bool) ($line_item['is_amount_discount'] ?? false)
                    ? $discount
                    : $gross * ($discount / 100))
                : 0), 2);
            $subtotal += $line_total;

            for ($position = 1; $position <= 3; $position++) {
                $name = trim((string) ($line_item["tax_name{$position}"] ?? ''));
                $rate = $this->number($line_item["tax_rate{$position}"] ?? 0);

                if ($name === '' || $rate == 0.0) {
                    continue;
                }

                $key = mb_strtolower($name) . '|' . $rate;
                $taxes[$key] = ($taxes[$key] ?? 0.0) + ($line_total * ($rate / 100));
            }
        }

        return round($subtotal + array_sum(array_map(
            fn(float $tax): float => round($tax, 2),
            $taxes,
        )), 2);
    }

    /** @param array<string, mixed> $payload @param array<int, object> $taxes */
    private function applyTaxes(array &$payload, array $taxes): void
    {
        foreach (array_slice($taxes, 0, 3) as $index => $tax) {
            $position = $index + 1;
            $payload["tax_name{$position}"] = (string) ($tax->name ?: "Tax {$tax->id}");
            $payload["tax_rate{$position}"] = $this->number($tax->value);
        }
    }

    private function isTaskLine(object $row): bool
    {
        $source = strtolower((string) $row->type . ' ' . (string) $row->item_type_table);

        return str_contains($source, 'time') || str_contains($source, 'task') || str_contains($source, 'project');
    }

    /** @return array{0: int, 1: int} */
    private function timeRange(object $time): array
    {
        if (isset($time->_start_timestamp, $time->_end_timestamp)) {
            return [(int) $time->_start_timestamp, (int) $time->_end_timestamp];
        }

        $date = $this->date($time->date);
        $minutes = max(0, (int) round($this->number($time->minutes)));

        if ($date === '') {
            return [0, 0];
        }

        try {
            $start_time = preg_match('/^\d{1,2}:\d{2}$/', (string) $time->start_time)
                ? (string) $time->start_time
                : '00:00';
            $start = CarbonImmutable::parse("{$date} {$start_time}", $this->timezone)->timestamp;
            $end = preg_match('/^\d{1,2}:\d{2}$/', (string) $time->end_time)
                ? CarbonImmutable::parse("{$date} {$time->end_time}", $this->timezone)->timestamp
                : $start + ($minutes * 60);

            if ($end <= $start && $minutes > 0) {
                $end = $start + ($minutes * 60);
            }

            return [$start, $end];
        } catch (\Throwable) {
            return [0, 0];
        }
    }

    private function documentWasSent(object $invoice): bool
    {
        return (int) $invoice->is_paid === 1
            || (int) $invoice->is_viewable === 1
            || (int) $invoice->last_sent > 0
            || (int) $invoice->has_sent_notification > 0
            || in_array(strtolower(trim((string) $invoice->status)), [
                'accepted',
                'converted',
                'declined',
                'open',
                'overdue',
                'paid',
                'partial',
                'rejected',
                'sent',
            ], true);
    }

    private function paymentTypeId(string $method): ?int
    {
        $method = mb_strtolower(trim($method));

        return match (true) {
            $method === '' => null,
            str_contains($method, 'paypal') => PaymentType::PAYPAL,
            str_contains($method, 'mastercard'), str_contains($method, 'master card') => PaymentType::MASTERCARD,
            str_contains($method, 'visa') => PaymentType::VISA,
            str_contains($method, 'american express'), str_contains($method, 'amex') => PaymentType::AMERICAN_EXPRESS,
            str_contains($method, 'discover') => PaymentType::DISCOVER,
            str_contains($method, 'credit card'), str_contains($method, 'card') => PaymentType::CREDIT_CARD_OTHER,
            str_contains($method, 'debit') => PaymentType::DEBIT,
            str_contains($method, 'ach') => PaymentType::ACH,
            str_contains($method, 'bank'), str_contains($method, 'wire') => PaymentType::BANK_TRANSFER,
            str_contains($method, 'cash') => PaymentType::CASH,
            str_contains($method, 'cheque'), str_contains($method, 'check') => PaymentType::CHECK,
            str_contains($method, 'money order') => PaymentType::MONEY_ORDER,
            str_contains($method, 'crypto') => PaymentType::CRYPTO,
            str_contains($method, 'venmo') => PaymentType::VENMO,
            str_contains($method, 'zelle') => PaymentType::ZELLE,
            default => null,
        };
    }

    private function frequencyId(string $frequency): int
    {
        $frequency = mb_strtolower(trim($frequency));
        $frequency = preg_replace('/[^a-z0-9]+/', ' ', $frequency) ?? $frequency;

        if (ctype_digit($frequency) && (int) $frequency >= 1 && (int) $frequency <= 12) {
            return (int) $frequency;
        }

        return match (true) {
            str_contains($frequency, 'daily'), str_contains($frequency, 'every day') => RecurringInvoice::FREQUENCY_DAILY,
            str_contains($frequency, 'fortnight'), str_contains($frequency, 'biweekly'), str_contains($frequency, 'two week') => RecurringInvoice::FREQUENCY_TWO_WEEKS,
            str_contains($frequency, 'four week') => RecurringInvoice::FREQUENCY_FOUR_WEEKS,
            str_contains($frequency, 'weekly'), str_contains($frequency, 'every week') => RecurringInvoice::FREQUENCY_WEEKLY,
            str_contains($frequency, 'two month'), str_contains($frequency, 'bimonth') => RecurringInvoice::FREQUENCY_TWO_MONTHS,
            str_contains($frequency, 'quarter'), str_contains($frequency, 'three month') => RecurringInvoice::FREQUENCY_THREE_MONTHS,
            str_contains($frequency, 'four month') => RecurringInvoice::FREQUENCY_FOUR_MONTHS,
            str_contains($frequency, 'six month'), str_contains($frequency, 'semiannual') => RecurringInvoice::FREQUENCY_SIX_MONTHS,
            str_contains($frequency, 'two year') => RecurringInvoice::FREQUENCY_TWO_YEARS,
            str_contains($frequency, 'three year') => RecurringInvoice::FREQUENCY_THREE_YEARS,
            str_contains($frequency, 'annual'), str_contains($frequency, 'year') => RecurringInvoice::FREQUENCY_ANNUALLY,
            default => RecurringInvoice::FREQUENCY_MONTHLY,
        };
    }

    private function nextSendDate(string $date, int $frequency): string
    {
        $next = $date !== '' ? CarbonImmutable::parse($date) : CarbonImmutable::today();

        while ($next->lt(CarbonImmutable::today())) {
            $next = match ($frequency) {
                RecurringInvoice::FREQUENCY_DAILY => $next->addDay(),
                RecurringInvoice::FREQUENCY_WEEKLY => $next->addWeek(),
                RecurringInvoice::FREQUENCY_TWO_WEEKS => $next->addWeeks(2),
                RecurringInvoice::FREQUENCY_FOUR_WEEKS => $next->addWeeks(4),
                RecurringInvoice::FREQUENCY_TWO_MONTHS => $next->addMonthsNoOverflow(2),
                RecurringInvoice::FREQUENCY_THREE_MONTHS => $next->addMonthsNoOverflow(3),
                RecurringInvoice::FREQUENCY_FOUR_MONTHS => $next->addMonthsNoOverflow(4),
                RecurringInvoice::FREQUENCY_SIX_MONTHS => $next->addMonthsNoOverflow(6),
                RecurringInvoice::FREQUENCY_ANNUALLY => $next->addYearNoOverflow(),
                RecurringInvoice::FREQUENCY_TWO_YEARS => $next->addYearsNoOverflow(2),
                RecurringInvoice::FREQUENCY_THREE_YEARS => $next->addYearsNoOverflow(3),
                default => $next->addMonthNoOverflow(),
            };
        }

        return $next->format('Y-m-d');
    }

    private function dueDateDays(string $date, string $due_date): string
    {
        if ($date === '' || $due_date === '') {
            return 'terms';
        }

        return (string) max(0, CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::parse($due_date), false));
    }

    private function isValidPartialDateRange(string $date, string $partial_due_date, string $due_date): bool
    {
        return $partial_due_date !== ''
            && $due_date !== ''
            && ($date === '' || $partial_due_date >= $date)
            && $partial_due_date < $due_date;
    }

    /**
     * Invoice Ninja supports one native partial amount on an invoice. The complete Pancake
     * installment schedule is retained in private notes so importing historical data cannot
     * accidentally activate future billing jobs.
     *
     * @param array<int, object> $partials
     */
    private function paymentScheduleNotes(array $partials): string
    {
        if ($partials === []) {
            return '';
        }

        $lines = array_map(function (object $partial): string {
            $amount = $this->number($partial->amount);
            $display_amount = (int) $partial->is_percentage === 1 ? "{$amount}%" : (string) $amount;
            $status = (int) $partial->is_paid === 1 ? 'paid' : 'unpaid';
            $due_date = $this->date($partial->due_date) ?: 'no due date';
            $notes = trim((string) $partial->notes);

            return trim("- {$due_date}: {$display_amount} ({$status})" . ($notes !== '' ? " — {$notes}" : ''));
        }, $partials);

        return "Pancake installment schedule:\n" . implode("\n", $lines);
    }

    private function languageCode(string $language): string
    {
        $language = mb_strtolower(trim($language));

        if (preg_match('/^[a-z]{2}(?:[-_][a-z]{2})?$/', $language)) {
            return substr($language, 0, 2);
        }

        return match ($language) {
            'english' => 'en',
            'french', 'français', 'francais' => 'fr',
            'german', 'deutsch' => 'de',
            'spanish', 'español', 'espanol' => 'es',
            'italian', 'italiano' => 'it',
            'dutch', 'nederlands' => 'nl',
            'portuguese', 'português', 'portugues' => 'pt',
            default => '',
        };
    }

    /** @param array<int, object> $meta */
    private function metadataNotes(array $meta): string
    {
        return implode("\n", array_map(
            fn(object $item): string => trim((string) ($item->label ?: $item->slug)) . ': ' . trim((string) $item->value),
            $meta,
        ));
    }

    /** @return array<int, string> */
    private function emails(string $emails): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn(string $email): string => mb_strtolower(trim($email)),
            preg_split('/[,;\s]+/', $emails) ?: [],
        ), fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $email !== 'test@test.com')));
    }

    private function date(mixed $value): string
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0' || $value === '0000-00-00 00:00:00') {
            return '';
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::createFromTimestamp((int) $value, $this->timezone)->format('Y-m-d');
            }

            return CarbonImmutable::parse((string) $value, $this->timezone)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function timestamp(mixed $value): int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return 0;
        }

        try {
            return is_numeric($value)
                ? (int) $value
                : CarbonImmutable::parse((string) $value, $this->timezone)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function number(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function compact(array $payload): array
    {
        return array_filter($payload, fn(mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /** @return array{path: string, entity: string, source_id: string, required: bool} */
    private function reference(
        string $path,
        DatabaseEntity $entity,
        string|int|null $source_id,
        bool $required = true,
    ): array {
        return [
            'path' => $path,
            'entity' => $entity->value,
            'source_id' => (string) $source_id,
            'required' => $required,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array{path: string, entity: string, source_id: string, required: bool}> $references
     * @param array<string, scalar> $query
     * @param array<string, mixed> $upload
     * @return array<string, mixed>
     */
    private function record(
        DatabaseEntity $entity,
        string|int $source_id,
        string $label,
        array $payload,
        array $references = [],
        array $query = [],
        array $upload = [],
    ): array {
        $payload = $this->preservePseudoLinks($payload);

        return compact('entity', 'source_id', 'label', 'payload', 'references', 'query', 'upload');
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function preservePseudoLinks(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->preservePseudoLinks($value);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $payload[$key] = preg_replace_callback(
                '/<((?:LINK\s*:\s*)?https?:\/\/[^<>\s]+)>/iu',
                fn(array $matches): string => '&lt;'
                    . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')
                    . '&gt;',
                $value,
            ) ?? $value;
        }

        return $payload;
    }
}

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

namespace App\Import\Harvest;

use App\Models\Country;
use App\Models\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

class CsvTransformer
{
    public function __construct(private readonly AddressParser $address_parser) {}

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @param array<int, Entity> $entities
     * @return array{
     *     records: array<string, array<int, array<string, mixed>>>,
     *     unmatched_contacts: array<int, array<string, string>>
     * }
     */
    public function transform(array $rows, array $entities, bool $resolve_currency): array
    {
        $records = [];

        foreach ($entities as $entity) {
            $records[$entity->value] = match ($entity) {
                Entity::Clients => [],
                Entity::Users => $this->userRecords($rows),
                Entity::Projects => $this->projectRecords($rows),
                Entity::Tasks => $this->taskTypeRecords($rows),
                Entity::TimeEntries => $this->timeEntryRecords($rows),
                Entity::ExpenseCategories => $this->expenseCategoryRecords($rows),
                Entity::Expenses => $this->expenseRecords($rows),
                Entity::Invoices => $this->documentRecords($rows['invoices'] ?? [], $rows['invoice_lines'] ?? [], Entity::Invoices),
                Entity::InvoicePayments => $this->paymentRecords($rows),
                Entity::Estimates => $this->documentRecords($rows['estimates'] ?? [], $rows['estimate_lines'] ?? [], Entity::Estimates),
            };
        }

        $unmatched_contacts = [];

        if (isset($records[Entity::Clients->value])) {
            [$records[Entity::Clients->value], $unmatched_contacts] = $this->clientRecords($rows, $resolve_currency);
        }

        return [
            'records' => $records,
            'unmatched_contacts' => $unmatched_contacts,
        ];
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, string>>}
     */
    private function clientRecords(array $rows, bool $resolve_currency): array
    {
        $client_rows = $rows['clients'] ?? [];
        $known_clients = [];

        foreach ($client_rows as $row) {
            $name = $this->value($row, ['client name', 'client']);

            if ($name !== '') {
                $known_clients[$this->key($name)] = true;
            }
        }

        foreach (['projects', 'time_entries', 'expenses', 'invoices', 'invoice_lines', 'invoice_payments', 'estimates', 'estimate_lines'] as $kind) {
            foreach ($rows[$kind] ?? [] as $row) {
                $name = $this->value($row, ['client', 'client name']);
                $key = $this->key($name);

                if ($key === '' || isset($known_clients[$key])) {
                    continue;
                }

                $client_rows[] = ['client name' => $name, 'address' => ''];
                $known_clients[$key] = true;
            }
        }

        $clients = [];

        foreach ($client_rows as $row) {
            $name = $this->value($row, ['client name', 'client']);

            if ($name === '') {
                continue;
            }

            $key = $this->key($name);
            $address = $this->address_parser->parse($this->value($row, ['address']));
            $currency_id = $resolve_currency
                ? $this->currencyIdForCountry($address['country_code'] ?? null)
                : null;
            $payload = array_merge(
                ['name' => $name],
                $address,
                $currency_id === null ? [] : ['settings' => ['currency_id' => $currency_id]],
                ['contacts' => []],
            );

            $clients[$key] = $this->record($key, $name, $payload);
        }

        $unmatched_contacts = [];
        $contact_keys = [];

        foreach ($rows['contacts'] ?? [] as $row) {
            $client_key = $this->key($this->value($row, ['client', 'client name']));

            if ($client_key === '' || ! isset($clients[$client_key])) {
                $unmatched_contacts[] = $row;

                continue;
            }

            $contact = $this->contactPayload($row);

            if ($contact === []) {
                continue;
            }

            $contact_key = $this->contactKey($contact);

            if (isset($contact_keys[$client_key][$contact_key])) {
                continue;
            }

            $contact_keys[$client_key][$contact_key] = true;
            $clients[$client_key]['payload']['contacts'][] = $contact;
        }

        foreach ($clients as &$client) {
            usort(
                $client['payload']['contacts'],
                fn(array $left, array $right): int => $this->contactPriority($left) <=> $this->contactPriority($right),
            );
        }
        unset($client);

        return [array_values($clients), $unmatched_contacts];
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function projectRecords(array $rows): array
    {
        $project_rows = $rows['projects'] ?? [];
        $known = [];

        foreach ($project_rows as $row) {
            $known[$this->projectKey(
                $this->value($row, ['client', 'client name']),
                $this->value($row, ['project', 'project name']),
            )] = true;
        }

        foreach (['time_entries', 'expenses'] as $kind) {
            foreach ($rows[$kind] ?? [] as $row) {
                $client = $this->value($row, ['client', 'client name']);
                $project = $this->value($row, ['project', 'project name']);
                $key = $this->projectKey($client, $project);

                if ($client === '' || $project === '' || isset($known[$key])) {
                    continue;
                }

                $project_rows[] = [
                    'client' => $client,
                    'project' => $project,
                    'project code' => $this->value($row, ['project code', 'code']),
                    'hourly rate' => $this->value($row, ['billable rate', 'hourly rate', 'project hourly rate']),
                ];
                $known[$key] = true;
            }
        }

        $records = [];
        $used_numbers = [];

        foreach ($project_rows as $row) {
            $client = $this->value($row, ['client', 'client name']);
            $name = $this->value($row, ['project', 'project name']);

            if ($client === '' || $name === '') {
                continue;
            }

            $key = $this->projectKey($client, $name);

            if (isset($records[$key])) {
                continue;
            }

            $number = $this->uniqueProjectNumber(
                $this->value($row, ['project code', 'code']),
                $used_numbers,
            );
            $payload = $this->compact([
                'name' => $name,
                'number' => $number,
                'task_rate' => $this->number($this->value($row, ['hourly rate', 'project hourly rate'])),
                'budgeted_hours' => $this->number($this->value($row, ['budgeted hours', 'budget hours', 'budget'])),
                'budgeted_amount' => $this->number($this->value($row, ['budgeted amount', 'cost budget', 'fee'])),
                'private_notes' => $this->value($row, ['project notes', 'notes']),
                'due_date' => $this->date($this->value($row, ['end date', 'ends on'])),
                'custom_value1' => $this->date($this->value($row, ['start date', 'starts on'])),
            ]);
            $payload['task_rate'] ??= 0.0;
            $records[$key] = $this->record($key, $name, $payload, [
                'client_id' => $this->reference(Entity::Clients, $this->key($client), $client),
            ]);
        }

        return array_values($records);
    }

    /** @param array<string, true> $used_numbers */
    private function uniqueProjectNumber(string $number, array &$used_numbers): string
    {
        if ($number === '') {
            return '';
        }

        $candidate = $number;
        $suffix = 2;

        while (isset($used_numbers[$this->key($candidate)])) {
            $candidate = "{$number}-{$suffix}";
            $suffix++;
        }

        $used_numbers[$this->key($candidate)] = true;

        return $candidate;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function taskTypeRecords(array $rows): array
    {
        $task_rows = $rows['task_types'] ?? [];

        foreach ($rows['time_entries'] ?? [] as $row) {
            $task_rows[] = ['task name' => $this->value($row, ['task', 'task name'])];
        }

        foreach (['invoice_lines', 'estimate_lines'] as $kind) {
            foreach ($rows[$kind] ?? [] as $row) {
                $task_rows[] = [
                    'name' => $this->value($row, ['item type', 'kind', 'type', 'product']),
                    'rate' => $this->value($row, ['item unit price', 'unit price', 'rate', 'cost']),
                ];
            }
        }

        $records = [];

        foreach ($task_rows as $row) {
            $name = $this->value($row, ['task name', 'task', 'name']);
            $key = $this->key($name);

            if ($key === '' || isset($records[$key])) {
                continue;
            }

            $rate = $this->number($this->value($row, ['default hourly rate', 'hourly rate', 'rate', 'item unit price', 'unit price']));
            $records[$key] = $this->record($key, $name, $this->compact([
                'product_key' => $name,
                'notes' => 'Harvest task or item type',
                'cost' => $rate,
                'price' => $rate,
            ]));
        }

        return array_values($records);
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function userRecords(array $rows): array
    {
        $user_rows = $rows['users'] ?? [];

        foreach (['time_entries', 'expenses'] as $kind) {
            foreach ($rows[$kind] ?? [] as $row) {
                $user_rows[] = $row;
            }
        }

        $records = [];

        foreach ($user_rows as $row) {
            $email = $this->value($row, ['email', 'email address']);

            if ($email === '') {
                continue;
            }

            $first_name = $this->value($row, ['first name']);
            $last_name = $this->value($row, ['last name']);
            $key = $this->key($email);

            if ($first_name === '' || $last_name === '' || isset($records[$key])) {
                continue;
            }

            $records[$key] = $this->record($key, trim("{$first_name} {$last_name}"), $this->compact([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $this->value($row, ['telephone', 'phone']),
                'company_user' => [
                    'is_admin' => false,
                    'permissions' => '',
                    'settings' => null,
                ],
            ]));
        }

        return array_values($records);
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function timeEntryRecords(array $rows): array
    {
        $records = [];

        foreach ($rows['time_entries'] ?? [] as $index => $row) {
            $date = $this->date($this->value($row, ['date', 'spent date']));
            $client = $this->value($row, ['client', 'client name']);
            $project = $this->value($row, ['project', 'project name']);
            $task = $this->value($row, ['task', 'task name']);
            $notes = $this->value($row, ['notes', 'description']);
            $hours = $this->number($this->value($row, ['hours', 'hours rounded', 'rounded hours']));

            if ($date === '' || $client === '' || $project === '') {
                continue;
            }

            [$start, $end] = $this->timeLog($date, $row, $hours);
            $label = trim("{$date} {$client} {$project} {$task}");
            $key = hash('sha256', $label . '|' . $notes . '|' . $index);
            $references = [
                'client_id' => $this->reference(Entity::Clients, $this->key($client), $client),
                'project_id' => $this->reference(
                    Entity::Projects,
                    $this->projectKey($client, $project),
                    "{$client}: {$project}",
                ),
            ];
            $email = $this->value($row, ['email', 'email address']);

            if ($email !== '') {
                $references['assigned_user_id'] = $this->reference(Entity::Users, $this->key($email));
            }

            $records[] = $this->record($key, $label, $this->compact([
                'description' => trim(implode("\n", array_filter([$task, $notes]))),
                'time_log' => [[$start, $end, $notes, $this->boolean($this->value($row, ['billable?', 'billable']), true)]],
                'rate' => $this->number($this->value($row, ['billable rate', 'rate'])),
                'custom_value1' => $task,
                'custom_value2' => trim($this->value($row, ['first name']) . ' ' . $this->value($row, ['last name'])),
            ]), $references);
        }

        return $records;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function expenseCategoryRecords(array $rows): array
    {
        $category_rows = $rows['expense_categories'] ?? [];

        foreach ($rows['expenses'] ?? [] as $row) {
            $category_rows[] = ['name' => $this->value($row, ['category', 'expense category'])];
        }

        $records = [];

        foreach ($category_rows as $row) {
            $name = $this->value($row, ['name', 'category', 'expense category']);
            $key = $this->key($name);

            if ($key === '' || isset($records[$key])) {
                continue;
            }

            $records[$key] = $this->record($key, $name, ['name' => $name]);
        }

        return array_values($records);
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function expenseRecords(array $rows): array
    {
        $records = [];

        foreach ($rows['expenses'] ?? [] as $index => $row) {
            $client = $this->value($row, ['client', 'client name']);
            $project = $this->value($row, ['project', 'project name']);
            $category = $this->value($row, ['category', 'expense category']);
            $date = $this->date($this->value($row, ['date', 'spent date']));
            $amount = $this->number($this->value($row, ['cost', 'total cost', 'amount']));

            if ($date === '' || $amount === null) {
                continue;
            }

            $label = trim("{$date} {$client} {$project} {$category}");
            $key = hash('sha256', $label . '|' . $amount . '|' . $index);
            $references = [];

            if ($client !== '') {
                $references['client_id'] = $this->reference(Entity::Clients, $this->key($client), $client);
            }

            if ($client !== '' && $project !== '') {
                $references['project_id'] = $this->reference(Entity::Projects, $this->projectKey($client, $project));
            }

            if ($category !== '') {
                $references['category_id'] = $this->reference(Entity::ExpenseCategories, $this->key($category));
            }

            $email = $this->value($row, ['email', 'email address']);

            if ($email !== '') {
                $references['assigned_user_id'] = $this->reference(Entity::Users, $this->key($email));
            }

            $records[] = $this->record($key, $label, $this->compact([
                'date' => $date,
                'amount' => $amount,
                'private_notes' => $this->value($row, ['notes', 'description']),
                'should_be_invoiced' => $this->boolean($this->value($row, ['billable?', 'billable'])),
                'currency_id' => $this->currencyIdForCode($this->value($row, ['currency', 'currency code'])),
                'custom_value1' => trim($this->value($row, ['first name']) . ' ' . $this->value($row, ['last name'])),
                'custom_value2' => $this->value($row, ['approval status']),
                'custom_value3' => $this->value($row, ['receipt url']),
            ]), $references);
        }

        return $records;
    }

    /**
     * @param array<int, array<string, string>> $document_rows
     * @param array<int, array<string, string>> $line_rows
     * @return array<int, array<string, mixed>>
     */
    private function documentRecords(array $document_rows, array $line_rows, Entity $entity): array
    {
        $lines_by_number = [];
        $lines_by_source = [];

        foreach ($line_rows as $row) {
            $number = $this->documentNumber($row, $entity);

            if ($number !== '') {
                $lines_by_number[$this->key($number)][] = $row;
                $lines_by_source[$this->documentSourceKey($row, $number)][] = $row;
            }
        }

        if ($document_rows === []) {
            foreach ($lines_by_source as $source_rows) {
                $document_rows[] = $source_rows[0];
            }
        }

        $documents = $this->documentMetadata($document_rows, $entity);
        $number_counts = array_count_values(array_column($documents, 'source_number'));
        $records = [];

        foreach ($documents as $document) {
            $row = $document['row'];
            $number = $document['number'];
            $source_number = $document['source_number'];
            $client = $this->value($row, ['client', 'client name']);

            if ($number === '' || $client === '') {
                continue;
            }

            $matched_lines = $lines_by_source[$document['source_key']] ?? [];

            if ($matched_lines === [] && ($number_counts[$source_number] ?? 0) === 1) {
                $matched_lines = $lines_by_number[$source_number] ?? [];
            }

            $line_items = array_map(
                fn(array $line): array => $this->lineItem($line),
                $matched_lines,
            );
            $has_line_item_taxes = $this->hasLineItemTaxes($line_items);
            $has_line_item_discounts = $this->hasLineItemDiscounts($line_items);

            if ($line_items === []) {
                $amount = $this->number($this->value($row, [
                    'subtotal',
                    $entity === Entity::Invoices ? 'invoice amount' : 'estimate amount',
                    'amount',
                    'total amount',
                ]));

                if ($amount !== null) {
                    $line_items[] = [
                        'product_key' => $this->value($row, ['subject']) ?: ucfirst($entity->value),
                        'notes' => $this->value($row, ['notes', 'subject']),
                        'quantity' => 1,
                        'cost' => $amount,
                        'type_id' => '1',
                    ];
                }
            }

            $subtotal = $this->number($this->value($row, ['subtotal']));
            $tax_rate1 = $has_line_item_taxes
                ? null
                : $this->exclusiveTaxRate(
                    $this->number($this->value($row, ['tax', 'tax amount', 'tax 1'])),
                    $subtotal,
                );
            $tax_rate2 = $has_line_item_taxes
                ? null
                : $this->exclusiveTaxRate(
                    $this->number($this->value($row, ['tax2', 'tax 2', 'tax 2 amount'])),
                    $subtotal,
                );
            $discount = $this->number($this->value($row, ['discount', 'discount percentage']));

            if ($has_line_item_discounts) {
                $discount = 0.0;
            }

            $payload = $this->compact([
                'number' => $number,
                'date' => $this->date($this->value($row, ['issue date', 'date'])),
                'due_date' => $this->date($this->value($row, ['due date'])),
                'po_number' => $this->value($row, ['purchase order', 'purchase order number', 'po number']),
                'discount' => $discount,
                'is_amount_discount' => $discount !== null && $this->value($row, ['discount percentage']) === '',
                'public_notes' => $this->value($row, ['notes']),
                'private_notes' => $this->documentPrivateNotes($row, $entity),
                'tax_name1' => $tax_rate1 ? $this->taxName('TAX', $tax_rate1) : '',
                'tax_rate1' => $tax_rate1,
                'tax_name2' => $tax_rate2 ? $this->taxName('TAX2', $tax_rate2) : '',
                'tax_rate2' => $tax_rate2,
                'uses_inclusive_taxes' => false,
                'line_items' => $line_items,
            ]);
            $records[] = $this->record(
                $this->key($number),
                $number,
                $payload,
                ['client_id' => $this->reference(Entity::Clients, $this->key($client), $client)],
                $entity === Entity::Estimates && $this->date($this->value($row, ['accepted date'])) !== '' ? ['approve'] : [],
            );
        }

        return $records;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function paymentRecords(array $rows): array
    {
        $invoices_by_source = [];
        $invoices_by_client_number = [];
        $invoices_by_number = [];

        foreach ($this->documentMetadata($rows['invoices'] ?? [], Entity::Invoices) as $invoice) {
            $client = $this->value($invoice['row'], ['client', 'client name']);
            $invoices_by_source[$invoice['source_key']] = $invoice;
            $invoices_by_client_number[$invoice['source_number'] . '|' . $this->key($client)][] = $invoice;
            $invoices_by_number[$invoice['source_number']][] = $invoice;
        }

        $records = [];

        foreach ($rows['invoice_payments'] ?? [] as $index => $row) {
            $source_invoice_number = $this->value($row, ['invoice id', 'invoice number', 'invoice']);
            $client = $this->value($row, ['client', 'client name']);
            $invoice = $invoices_by_source[$this->documentSourceKey($row, $source_invoice_number)]
                ?? $this->onlyDocument($invoices_by_client_number[$this->key($source_invoice_number) . '|' . $this->key($client)] ?? [])
                ?? $this->onlyDocument($invoices_by_number[$this->key($source_invoice_number)] ?? []);
            $invoice_number = $invoice['number'] ?? $source_invoice_number;
            $client = $client ?: ($invoice ? $this->value($invoice['row'], ['client', 'client name']) : '');
            $amount = $this->number($this->value($row, ['amount paid', 'payment amount', 'amount']));

            if ($invoice_number === '' || $client === '' || $amount === null) {
                continue;
            }

            $date = $this->date($this->value($row, ['payment date', 'date']));
            $label = trim("{$invoice_number} {$date} {$amount}");
            $key = hash('sha256', $label . '|' . $index);
            $records[] = $this->record($key, $label, $this->compact([
                'amount' => $amount,
                'date' => $date,
                'transaction_reference' => $this->value($row, ['notes', 'reference', 'payment method']),
                'invoices' => [['amount' => $amount]],
            ]), [
                'client_id' => $this->reference(Entity::Clients, $this->key($client), $client),
                'invoices.0.invoice_id' => $this->reference(Entity::Invoices, $this->key($invoice_number), $invoice_number),
            ]);
        }

        return $records;
    }

    /**
     * @param array<int, array<string, string>> $document_rows
     * @return array<int, array{
     *     row: array<string, string>,
     *     source_number: string,
     *     source_key: string,
     *     number: string
     * }>
     */
    private function documentMetadata(array $document_rows, Entity $entity): array
    {
        $documents = [];
        $used_numbers = [];

        foreach ($document_rows as $row) {
            $source_number = $this->documentNumber($row, $entity);

            if ($source_number === '') {
                continue;
            }

            $number = $source_number;
            $suffix = 1;

            while (isset($used_numbers[$this->key($number)])) {
                $suffix++;
                $number = "{$source_number}-{$suffix}";
            }

            $used_numbers[$this->key($number)] = true;
            $documents[] = [
                'row' => $row,
                'source_number' => $this->key($source_number),
                'source_key' => $this->documentSourceKey($row, $source_number),
                'number' => $number,
            ];
        }

        return $documents;
    }

    /** @param array<string, string> $row */
    private function documentNumber(array $row, Entity $entity): string
    {
        return $this->value($row, [
            $entity === Entity::Invoices ? 'invoice id' : 'estimate id',
            $entity === Entity::Invoices ? 'invoice number' : 'estimate number',
            'number',
            'id',
        ]);
    }

    /** @param array<string, string> $row */
    private function documentSourceKey(array $row, string $number): string
    {
        return implode('|', [
            $this->key($number),
            $this->key($this->value($row, ['client', 'client name'])),
            $this->date($this->value($row, ['invoice issue date', 'issue date', 'date'])),
        ]);
    }

    /**
     * @param array<int, array{
     *     row: array<string, string>,
     *     source_number: string,
     *     source_key: string,
     *     number: string
     * }> $documents
     * @return array{
     *     row: array<string, string>,
     *     source_number: string,
     *     source_key: string,
     *     number: string
     * }|null
     */
    private function onlyDocument(array $documents): ?array
    {
        return count($documents) === 1 ? $documents[0] : null;
    }

    /** @param array<string, string> $row */
    private function lineItem(array $row): array
    {
        $quantity = $this->number($this->value($row, ['item quantity', 'quantity', 'hours'])) ?? 1;
        $cost = $this->number($this->value($row, ['item unit price', 'unit price', 'rate', 'cost'])) ?? 0;
        $item_amount = $this->number($this->value($row, ['item amount', 'amount'])) ?? ($quantity * $cost);
        $tax_rate1 = $this->exclusiveTaxRate(
            $this->number($this->value($row, ['item tax', 'tax amount', 'tax'])),
            $item_amount,
        );
        $tax_rate2 = $this->exclusiveTaxRate(
            $this->number($this->value($row, ['item tax2', 'item tax 2', 'tax 2 amount', 'tax2'])),
            $item_amount,
        );

        return $this->compact([
            'product_key' => $this->value($row, ['item type', 'kind', 'type', 'product']),
            'notes' => $this->value($row, ['item description', 'description', 'notes']),
            'quantity' => $quantity,
            'cost' => $cost,
            'discount' => $this->number($this->value($row, ['item discount'])),
            'tax_name1' => $tax_rate1 ? $this->taxName('TAX', $tax_rate1) : '',
            'tax_rate1' => $tax_rate1,
            'tax_name2' => $tax_rate2 ? $this->taxName('TAX2', $tax_rate2) : '',
            'tax_rate2' => $tax_rate2,
            'type_id' => '1',
        ]);
    }

    /** @param array<int, array<string, mixed>> $line_items */
    private function hasLineItemTaxes(array $line_items): bool
    {
        foreach ($line_items as $line_item) {
            if (($line_item['tax_rate1'] ?? 0) > 0 || ($line_item['tax_rate2'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $line_items */
    private function hasLineItemDiscounts(array $line_items): bool
    {
        foreach ($line_items as $line_item) {
            if (($line_item['discount'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $row */
    private function documentPrivateNotes(array $row, Entity $entity): string
    {
        $notes = $this->value($row, ['private notes', 'subject']);

        if ($entity !== Entity::Estimates) {
            return $notes;
        }

        $accepted_date = $this->value($row, ['accepted date']);
        $declined_date = $this->value($row, ['declined date']);

        if ($accepted_date === '' && $declined_date === '') {
            return $notes;
        }

        $harvest_status = implode("\n", [
            'Harvest quote status:',
            'Accepted Date: ' . ($accepted_date !== '' ? $accepted_date : 'Not set'),
            'Declined Date: ' . ($declined_date !== '' ? $declined_date : 'Not set'),
        ]);

        return trim(implode("\n\n", array_filter([$notes, $harvest_status])));
    }

    private function exclusiveTaxRate(?float $tax_amount, ?float $subtotal): ?float
    {
        if ($tax_amount === null || $tax_amount == 0.0 || $subtotal === null || $subtotal <= 0.0) {
            return null;
        }

        return round(($tax_amount / $subtotal) * 100, 6);
    }

    private function taxName(string $prefix, float $rate): string
    {
        $formatted_rate = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');

        return "{$prefix} ({$formatted_rate}%)";
    }

    /** @param array<string, string> $row */
    private function contactPayload(array $row): array
    {
        $office_phone = $this->value($row, ['office phone']);
        $mobile_phone = $this->value($row, ['mobile phone']);
        $invoice_email_default = strtolower($this->value($row, ['invoice email default']));
        $contact = $this->compact([
            'first_name' => $this->value($row, ['first name']),
            'last_name' => $this->value($row, ['last name']),
            'email' => $this->value($row, ['email']),
            'phone' => $office_phone !== '' ? $office_phone : $mobile_phone,
            'custom_value1' => $this->value($row, ['title']),
            'custom_value2' => $mobile_phone,
            'custom_value3' => $this->value($row, ['fax']),
            'custom_value4' => $this->value($row, ['invoice email default']),
        ]);

        if (in_array($invoice_email_default, ['recipient', 'to', 'yes'], true)) {
            $contact['send_email'] = true;
            $contact['cc_only'] = false;
        } elseif (in_array($invoice_email_default, ['cc', 'bcc'], true)) {
            $contact['send_email'] = false;
            $contact['cc_only'] = true;
        } elseif (in_array($invoice_email_default, ['none', 'no'], true)) {
            $contact['send_email'] = false;
            $contact['cc_only'] = false;
        }

        return $contact;
    }

    /** @param array<string, string> $row */
    private function timeLog(string $date, array $row, ?float $hours): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $started_time = $this->value($row, ['started time', 'start time']);
        $ended_time = $this->value($row, ['ended time', 'end time']);
        $start = CarbonImmutable::parse($date . ' ' . ($started_time ?: '00:00:00'), $timezone);
        $end = $ended_time !== ''
            ? CarbonImmutable::parse($date . ' ' . $ended_time, $timezone)
            : $start->addSeconds(max(1, (int) round(($hours ?? 0) * 3600)));

        return [$start->timestamp, $end->timestamp];
    }

    private function currencyIdForCountry(?string $country_code): ?string
    {
        $country_code = strtoupper(trim($country_code ?? ''));

        if ($country_code === '') {
            return null;
        }

        /** @var Collection<int, Country> $countries */
        $countries = app('countries');
        $country = $countries->firstWhere('iso_3166_2', $country_code);

        if (! $country || ! $country->currency_code) {
            throw new InvalidArgumentException("Unable to determine a currency for country code: {$country_code}");
        }

        return $this->currencyIdForCode($country->currency_code)
            ?? throw new RuntimeException("Invoice Ninja currency was not found for country code: {$country_code}");
    }

    private function currencyIdForCode(string $currency_code): ?string
    {
        if ($currency_code === '') {
            return null;
        }

        /** @var Collection<int, Currency> $currencies */
        $currencies = app('currencies');
        $currency = $currencies->firstWhere('code', strtoupper($currency_code));

        return $currency ? (string) $currency->id : null;
    }

    /** @param array<string, string> $row */
    private function value(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            if (! isset($row[$header])) {
                continue;
            }

            $value = preg_replace('/^\s+|\s+$/u', '', $row[$header]) ?? trim($row[$header]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function number(string $value): ?float
    {
        if (trim($value) === '') {
            return null;
        }

        $negative = str_contains($value, '(') && str_contains($value, ')');
        $number = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value)) ?? '';

        if ($number === '' || ! is_numeric($number)) {
            return null;
        }

        return (float) $number * ($negative ? -1 : 1);
    }

    private function boolean(string $value, bool $default = false): bool
    {
        if (trim($value) === '') {
            return $default;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'billable', 'active', 'employee'], true);
    }

    private function date(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array<string, mixed> $payload */
    private function compact(array $payload): array
    {
        return array_filter($payload, fn(mixed $value): bool => $value !== '' && $value !== null);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, array{entity: string, key: string, name?: string}> $references
     * @param array<int, string> $actions
     * @return array<string, mixed>
     */
    private function record(string $key, string $label, array $payload, array $references = [], array $actions = []): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'payload' => $payload,
            'references' => $references,
            'actions' => $actions,
        ];
    }

    /** @return array{entity: string, key: string, name?: string} */
    private function reference(Entity $entity, string $key, ?string $name = null): array
    {
        return array_filter([
            'entity' => $entity->value,
            'key' => $key,
            'name' => $name,
        ], fn(?string $value): bool => $value !== null && $value !== '');
    }

    private function key(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function projectKey(string $client, string $project): string
    {
        return $this->key($client) . '|' . $this->key($project);
    }

    /** @param array<string, mixed> $contact */
    private function contactKey(array $contact): string
    {
        if (isset($contact['email'])) {
            return 'email:' . $this->key((string) $contact['email']);
        }

        return 'contact:' . $this->key(implode('|', [
            (string) ($contact['first_name'] ?? ''),
            (string) ($contact['last_name'] ?? ''),
            (string) ($contact['phone'] ?? ''),
        ]));
    }

    /** @param array<string, mixed> $contact */
    private function contactPriority(array $contact): int
    {
        if (($contact['send_email'] ?? false) === true && ($contact['cc_only'] ?? false) === false) {
            return 0;
        }

        return ($contact['cc_only'] ?? false) === true ? 1 : 2;
    }
}

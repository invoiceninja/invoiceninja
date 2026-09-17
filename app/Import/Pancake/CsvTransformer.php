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

class CsvTransformer
{
    private const MAX_LINE_ITEMS = 44;

    private const MAX_PAYMENTS = 8;

    private const IGNORED_EMAIL_ADDRESSES = ['test@test.com'];

    public function __construct(private readonly AddressParser $address_parser) {}

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @param array<int, Entity> $entities
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function transform(array $rows, array $entities): array
    {
        [$clients, $client_aliases] = $this->clientRecords($rows);
        $documents = $this->documentMetadata($rows['invoices'] ?? [], $client_aliases);
        $records = [];

        foreach ($entities as $entity) {
            $records[$entity->value] = match ($entity) {
                Entity::Clients => $clients,
                Entity::Invoices => $this->documentRecords($documents, Entity::Invoices),
                Entity::RecurringInvoices => $this->documentRecords($documents, Entity::RecurringInvoices),
                Entity::Payments => $this->paymentRecords($documents),
            };
        }

        return $records;
    }

    /**
     * @param array<string, array<int, array<string, string>>> $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, string>}
     */
    private function clientRecords(array $rows): array
    {
        $clients = [];
        $aliases = [];
        $contact_keys = [];

        foreach ($rows['clients'] ?? [] as $row) {
            $name = $this->clientName($row);
            $key = $this->key($name);

            if ($key === '') {
                continue;
            }

            if (! isset($clients[$key])) {
                $clients[$key] = $this->record($key, $name, $this->clientPayload($row), aliases: []);
            } else {
                $clients[$key]['payload'] = $this->mergeMissing(
                    $clients[$key]['payload'],
                    $this->clientPayload($row),
                );
            }

            $client_aliases = array_values(array_unique(array_filter([
                $name,
                $this->value($row, ['company']),
                $this->fullName($row),
                ...$this->emailAddresses($row),
            ])));

            foreach ($client_aliases as $alias) {
                $alias_key = $this->key($alias);
                $aliases[$alias_key] ??= $key;
                $clients[$key]['aliases'][$alias_key] = $alias;
            }

            foreach ($this->contactPayloads($row) as $contact) {
                $contact_key = $this->contactKey($contact);

                if (! isset($contact_keys[$key][$contact_key])) {
                    $clients[$key]['payload']['contacts'][] = $contact;
                    $contact_keys[$key][$contact_key] = true;
                }
            }
        }

        foreach ($rows['invoices'] ?? [] as $row) {
            $name = $this->value($row, ['client']);
            $source_key = $this->key($name);

            if ($source_key === '' || isset($aliases[$source_key])) {
                continue;
            }

            $aliases[$source_key] = $source_key;
            $clients[$source_key] = $this->record(
                $source_key,
                $name,
                ['name' => $name, 'contacts' => []],
                aliases: [$source_key => $name],
            );
        }

        $currencies = [];

        foreach ($rows['invoices'] ?? [] as $row) {
            $source_key = $this->key($this->value($row, ['client']));
            $client_key = $aliases[$source_key] ?? $source_key;
            $currency = strtoupper($this->value($row, ['currency']));

            if ($client_key !== '' && preg_match('/^[A-Z]{3}$/', $currency)) {
                $currencies[$client_key][$currency] = true;
            }
        }

        foreach ($clients as $key => &$client) {
            if (count($currencies[$key] ?? []) === 1) {
                $client['payload']['currency_code'] = array_key_first($currencies[$key]);
            }

            $client['aliases'] = array_keys($client['aliases']);
        }
        unset($client);

        return [array_values($clients), $aliases];
    }

    /** @param array<string, string> $row */
    private function clientPayload(array $row): array
    {
        $phone = $this->value($row, ['telephone number']);
        $payload = array_merge(
            ['name' => $this->clientName($row)],
            $this->address_parser->parse($this->value($row, ['address'])),
            $this->compact([
                'phone' => $phone,
                'website' => $this->value($row, ['website url']),
                'private_notes' => $this->value($row, ['notes']),
                'language_code' => $this->languageCode($this->value($row, ['language'])),
                'custom_value1' => $this->value($row, ['client area url']),
                'custom_value2' => $this->value($row, ['client area passphrase']),
                'custom_value3' => $this->matchingValue($row, '/\btax\b.*\(\s*\d+(?:\.\d+)?\s*%\s*\)$/i'),
                'custom_value4' => $this->value($row, ['ocreations client']),
            ]),
            ['contacts' => []],
        );

        if ($phone === '' && isset($payload['phone'])) {
            $payload['phone'] = (string) $payload['phone'];
        }

        return $payload;
    }

    /**
     * @param array<string, string> $row
     * @return array<int, array<string, mixed>>
     */
    private function contactPayloads(array $row): array
    {
        $payload = $this->compact([
            'first_name' => $this->value($row, ['first name']),
            'last_name' => $this->value($row, ['last name']),
            'phone' => $this->value($row, ['telephone number', 'mobile number']),
            'custom_value1' => $this->value($row, ['title']),
            'custom_value2' => $this->value($row, ['mobile number']),
            'custom_value3' => $this->value($row, ['fax number']),
        ]);
        $emails = $this->emailAddresses($row);

        if ($emails === []) {
            return $payload === [] ? [] : [$payload];
        }

        return array_map(
            fn(string $email): array => array_merge($payload, ['email' => $email]),
            $emails,
        );
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string, string> $client_aliases
     * @return array<int, array{
     *     row: array<string, string>,
     *     source_index: int,
     *     entity: Entity,
     *     key: string,
     *     number: string,
     *     client: string,
     *     client_key: string
     * }>
     */
    private function documentMetadata(array $rows, array $client_aliases): array
    {
        $documents = [];
        $used_numbers = [];

        foreach ($rows as $index => $row) {
            $source_number = $this->value($row, ['invoice #']);
            $client = $this->value($row, ['client']);

            if ($source_number === '' || $client === '') {
                continue;
            }

            $entity = $this->isRecurringTemplate($row)
                ? Entity::RecurringInvoices
                : Entity::Invoices;
            $number = $source_number;
            $suffix = 1;

            while (isset($used_numbers[$entity->value][$this->key($number)])) {
                $suffix++;
                $number = "{$source_number}-{$suffix}";
            }

            $key = $this->key($number);
            $used_numbers[$entity->value][$key] = true;
            $source_client_key = $this->key($client);
            $documents[] = [
                'row' => $row,
                'source_index' => $index,
                'entity' => $entity,
                'key' => $key,
                'number' => $number,
                'client' => $client,
                'client_key' => $client_aliases[$source_client_key] ?? $source_client_key,
            ];
        }

        return $documents;
    }

    /**
     * @param array<int, array{
     *     row: array<string, string>,
     *     source_index: int,
     *     entity: Entity,
     *     key: string,
     *     number: string,
     *     client: string,
     *     client_key: string
     * }> $documents
     * @return array<int, array<string, mixed>>
     */
    private function documentRecords(array $documents, Entity $entity): array
    {
        $records = [];

        foreach ($documents as $document) {
            if ($document['entity'] !== $entity) {
                continue;
            }

            $row = $document['row'];
            $payload = $this->documentPayload($row, $document['number'], $entity);
            $record = $this->record(
                $document['key'],
                $document['number'],
                $payload,
                ['client_id' => $this->reference(Entity::Clients, $document['client_key'], $document['client'])],
            );

            if ($entity === Entity::Invoices) {
                $record['mark_sent'] = $this->boolean($this->value($row, ['show in client area?']));
            }

            $records[] = $record;
        }

        return $records;
    }

    /** @param array<string, string> $row */
    private function documentPayload(array $row, string $number, Entity $entity): array
    {
        $date = $this->date($this->value($row, ['date of creation']));
        $due_date = $this->date($this->value($row, ['due date']));
        $payload = $this->compact([
            'number' => $number,
            'date' => $date,
            'due_date' => $entity === Entity::Invoices ? $due_date : null,
            'exchange_rate' => $this->number($this->value($row, ['exchange rate'])),
            'public_notes' => $this->value($row, ['notes']),
            'private_notes' => $this->documentPrivateNotes($row),
            'uses_inclusive_taxes' => false,
            'line_items' => $this->lineItems($row),
        ]);

        if ($entity === Entity::RecurringInvoices) {
            $frequency_id = $this->frequencyId($this->value($row, ['recurrence frequency']));
            $payload['frequency_id'] = $frequency_id;
            $payload['next_send_date'] = $this->nextSendDate($date, $frequency_id);
            $payload['remaining_cycles'] = RecurringInvoice::RECURS_INDEFINITELY;
            $payload['due_date_days'] = $this->dueDateDays($date, $due_date);
        }

        return $payload;
    }

    /** @param array<string, string> $row */
    private function lineItems(array $row): array
    {
        $line_items = [];

        for ($item = 1; $item <= self::MAX_LINE_ITEMS; $item++) {
            $name = $this->value($row, ["item #{$item} name"]);
            $description = $this->value($row, ["item #{$item} description"]);
            $quantity_value = $this->value($row, ["item #{$item} quantity"]);
            $rate_value = $this->value($row, ["item #{$item} rate"]);
            $total_value = $this->value($row, ["item #{$item} total amount (without tax)"]);
            $discount_value = $this->value($row, ["item #{$item} gross discount"]);
            [$tax_header, $tax_value] = $this->matchingEntry($row, '/^item #' . $item . ' tax #1(?:\s|$)/i');

            if ($name === ''
                && $description === ''
                && $quantity_value === ''
                && $rate_value === ''
                && $total_value === ''
                && $discount_value === ''
                && $tax_value === '') {
                continue;
            }

            $quantity = $this->number($quantity_value) ?? 1.0;
            $total = $this->number($total_value);
            $cost = $this->number($rate_value);

            if ($cost === null && $total !== null && $quantity != 0.0) {
                $cost = $total / $quantity;
            }

            $cost ??= 0.0;
            $discount = $this->discountRate($discount_value, $quantity * $cost);
            [$tax_name, $tax_rate] = $this->tax($tax_header, $tax_value, $total);
            $line_items[] = $this->compact([
                'product_key' => $name !== '' ? $name : "Item {$item}",
                'notes' => $description,
                'quantity' => $quantity,
                'cost' => $cost,
                'discount' => $discount,
                'tax_name1' => $tax_name,
                'tax_rate1' => $tax_rate,
                'type_id' => '1',
            ]);
        }

        if ($line_items !== []) {
            return $line_items;
        }

        $total = $this->number($this->value($row, ['total amount (without tax)']));

        if ($total === null) {
            return [];
        }

        return [[
            'product_key' => $this->value($row, ['description']) ?: 'Invoice',
            'notes' => $this->value($row, ['notes']),
            'quantity' => 1.0,
            'cost' => $total,
            'type_id' => '1',
        ]];
    }

    /**
     * @param array<int, array{
     *     row: array<string, string>,
     *     source_index: int,
     *     entity: Entity,
     *     key: string,
     *     number: string,
     *     client: string,
     *     client_key: string
     * }> $documents
     * @return array<int, array<string, mixed>>
     */
    private function paymentRecords(array $documents): array
    {
        $records = [];

        foreach ($documents as $document) {
            if ($document['entity'] !== Entity::Invoices) {
                continue;
            }

            $row = $document['row'];
            $paid_installments = 0;

            for ($payment = 1; $payment <= self::MAX_PAYMENTS; $payment++) {
                $amount = $this->number($this->value($row, ["payment #{$payment} gross amount"]));
                $paid = $this->value($row, ["payment #{$payment} is paid"]);
                $payment_date = $this->date($this->value($row, ["payment #{$payment} payment date"]));

                if ($amount === null
                    || $amount <= 0
                    || ($paid !== '' && ! $this->boolean($paid))
                    || ($paid === '' && $payment_date === '')) {
                    continue;
                }

                $records[] = $this->paymentRecord($document, $payment, $amount, $payment_date);
                $paid_installments++;
            }

            if ($paid_installments > 0) {
                continue;
            }

            $amount_paid = $this->number($this->value($row, ['amount paid']));

            if ($amount_paid !== null && $amount_paid > 0) {
                $records[] = $this->summaryPaymentRecord($document, $amount_paid);
            }
        }

        return $records;
    }

    /**
     * @param array{
     *     row: array<string, string>,
     *     source_index: int,
     *     entity: Entity,
     *     key: string,
     *     number: string,
     *     client: string,
     *     client_key: string
     * } $document
     * @return array<string, mixed>
     */
    private function paymentRecord(array $document, int $payment, float $amount, string $date): array
    {
        $row = $document['row'];
        $method = $this->value($row, ["payment #{$payment} payment method"]);
        $transaction_id = $this->value($row, ["payment #{$payment} transaction id"]);
        $due_date = $this->date($this->value($row, ["payment #{$payment} due date"]));
        $fee = $this->number($this->value($row, ["payment #{$payment} transaction fee"]));
        $date = $date ?: $this->date($this->value($row, ['payment date', 'date of creation']));
        $private_notes = array_filter([
            $method !== '' ? "Pancake payment method: {$method}" : '',
            $due_date !== '' ? "Pancake payment due date: {$due_date}" : '',
            $fee !== null ? "Pancake transaction fee: {$fee}" : '',
        ], fn(string $value): bool => $value !== '');
        $key = hash('sha256', implode('|', [
            $document['source_index'],
            $document['key'],
            $payment,
            $amount,
            $date,
            $transaction_id,
        ]));

        return $this->record(
            $key,
            trim("{$document['number']} {$date} {$amount}"),
            $this->compact([
                'amount' => $amount,
                'date' => $date,
                'type_id' => $this->paymentTypeId($method),
                'transaction_reference' => $transaction_id !== '' ? $transaction_id : $method,
                'private_notes' => implode("\n", $private_notes),
                'invoices' => [['amount' => $amount]],
            ]),
            [
                'client_id' => $this->reference(Entity::Clients, $document['client_key'], $document['client']),
                'invoices.0.invoice_id' => $this->reference(Entity::Invoices, $document['key'], $document['number']),
            ],
        );
    }

    /**
     * @param array{
     *     row: array<string, string>,
     *     source_index: int,
     *     entity: Entity,
     *     key: string,
     *     number: string,
     *     client: string,
     *     client_key: string
     * } $document
     * @return array<string, mixed>
     */
    private function summaryPaymentRecord(array $document, float $amount): array
    {
        $date = $this->date($this->value($document['row'], ['payment date', 'date of creation']));
        $key = hash('sha256', implode('|', [
            $document['source_index'],
            $document['key'],
            'summary',
            $amount,
            $date,
        ]));

        return $this->record(
            $key,
            trim("{$document['number']} {$date} {$amount}"),
            $this->compact([
                'amount' => $amount,
                'date' => $date,
                'private_notes' => 'Imported from the Pancake invoice payment summary.',
                'invoices' => [['amount' => $amount]],
            ]),
            [
                'client_id' => $this->reference(Entity::Clients, $document['client_key'], $document['client']),
                'invoices.0.invoice_id' => $this->reference(Entity::Invoices, $document['key'], $document['number']),
            ],
        );
    }

    /** @param array<string, string> $row */
    private function isRecurringTemplate(array $row): bool
    {
        return $this->boolean($this->value($row, ['recurring?']))
            && ! $this->boolean($this->value($row, ['recurrence of a recurring invoice?']));
    }

    /** @param array<string, string> $row */
    private function documentPrivateNotes(array $row): string
    {
        $notes = [$this->value($row, ['description'])];

        if ($this->boolean($this->value($row, ['auto-send?']))) {
            $notes[] = 'Pancake auto-send: Yes';
        }

        if ($this->boolean($this->value($row, ['recurrence of a recurring invoice?']))) {
            $notes[] = 'Pancake recurring invoice occurrence: Yes';
        }

        return implode("\n\n", array_filter($notes));
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
            str_contains($frequency, 'fortnight'), str_contains($frequency, 'biweekly'), str_contains($frequency, 'two week'), str_contains($frequency, '2 week') => RecurringInvoice::FREQUENCY_TWO_WEEKS,
            str_contains($frequency, 'four week'), str_contains($frequency, '4 week') => RecurringInvoice::FREQUENCY_FOUR_WEEKS,
            str_contains($frequency, 'weekly'), str_contains($frequency, 'every week') => RecurringInvoice::FREQUENCY_WEEKLY,
            str_contains($frequency, 'two month'), str_contains($frequency, '2 month'), str_contains($frequency, 'bimonth') => RecurringInvoice::FREQUENCY_TWO_MONTHS,
            str_contains($frequency, 'quarter'), str_contains($frequency, 'three month'), str_contains($frequency, '3 month') => RecurringInvoice::FREQUENCY_THREE_MONTHS,
            str_contains($frequency, 'four month'), str_contains($frequency, '4 month') => RecurringInvoice::FREQUENCY_FOUR_MONTHS,
            str_contains($frequency, 'six month'), str_contains($frequency, '6 month'), str_contains($frequency, 'semiannual') => RecurringInvoice::FREQUENCY_SIX_MONTHS,
            str_contains($frequency, 'two year'), str_contains($frequency, '2 year') => RecurringInvoice::FREQUENCY_TWO_YEARS,
            str_contains($frequency, 'three year'), str_contains($frequency, '3 year') => RecurringInvoice::FREQUENCY_THREE_YEARS,
            str_contains($frequency, 'annual'), str_contains($frequency, 'year') => RecurringInvoice::FREQUENCY_ANNUALLY,
            default => RecurringInvoice::FREQUENCY_MONTHLY,
        };
    }

    private function nextSendDate(string $source_date, int $frequency_id): string
    {
        try {
            $date = $source_date !== ''
                ? CarbonImmutable::parse($source_date)->startOfDay()
                : CarbonImmutable::today();
        } catch (\Throwable) {
            $date = CarbonImmutable::today();
        }

        $today = CarbonImmutable::today();
        $source = $date;
        $occurrences = 0;

        while ($date->lt($today)) {
            $occurrences++;
            $date = match ($frequency_id) {
                RecurringInvoice::FREQUENCY_DAILY => $source->addDays($occurrences),
                RecurringInvoice::FREQUENCY_WEEKLY => $source->addWeeks($occurrences),
                RecurringInvoice::FREQUENCY_TWO_WEEKS => $source->addWeeks($occurrences * 2),
                RecurringInvoice::FREQUENCY_FOUR_WEEKS => $source->addWeeks($occurrences * 4),
                RecurringInvoice::FREQUENCY_MONTHLY => $source->addMonthsNoOverflow($occurrences),
                RecurringInvoice::FREQUENCY_TWO_MONTHS => $source->addMonthsNoOverflow($occurrences * 2),
                RecurringInvoice::FREQUENCY_THREE_MONTHS => $source->addMonthsNoOverflow($occurrences * 3),
                RecurringInvoice::FREQUENCY_FOUR_MONTHS => $source->addMonthsNoOverflow($occurrences * 4),
                RecurringInvoice::FREQUENCY_SIX_MONTHS => $source->addMonthsNoOverflow($occurrences * 6),
                RecurringInvoice::FREQUENCY_ANNUALLY => $source->addYearsNoOverflow($occurrences),
                RecurringInvoice::FREQUENCY_TWO_YEARS => $source->addYearsNoOverflow($occurrences * 2),
                RecurringInvoice::FREQUENCY_THREE_YEARS => $source->addYearsNoOverflow($occurrences * 3),
                default => $source->addMonthsNoOverflow($occurrences),
            };
        }

        return $date->format('Y-m-d');
    }

    private function dueDateDays(string $date, string $due_date): string
    {
        if ($date === '' || $due_date === '') {
            return 'terms';
        }

        try {
            return (string) max(0, CarbonImmutable::parse($date)->diffInDays(
                CarbonImmutable::parse($due_date),
                false,
            ));
        } catch (\Throwable) {
            return 'terms';
        }
    }

    /** @return array{0: string, 1: ?float} */
    private function tax(string $header, string $value, ?float $line_total): array
    {
        if ($value === '') {
            return ['', null];
        }

        $name = preg_replace('/^item #\d+ tax #1\s*/i', '', $header) ?? '';
        $name = trim($name);

        if (str_starts_with($name, '(') && str_ends_with($name, ')')) {
            $name = substr($name, 1, -1);
        }

        $rate = null;

        if (str_contains($value, '%')) {
            $rate = $this->number($value);
        } elseif (preg_match('/\(\s*(\d+(?:\.\d+)?)\s*%\s*\)/', $header, $matches)) {
            $rate = (float) $matches[1];
        } elseif ($line_total !== null && $line_total != 0.0) {
            $tax_amount = $this->number($value);
            $rate = $tax_amount === null ? null : abs(($tax_amount / $line_total) * 100);
        } else {
            $rate = $this->number($value);
        }

        if ($rate === null || $rate == 0.0) {
            return ['', null];
        }

        $name = $name !== '' ? mb_convert_case($name, MB_CASE_TITLE) : 'Tax';
        $name = preg_replace('/^Pa\b/', 'PA', $name) ?? $name;

        return [$name, round($rate, 6)];
    }

    private function discountRate(string $value, float $gross): ?float
    {
        $discount = $this->number($value);

        if ($discount === null) {
            return null;
        }

        if (str_contains($value, '%')) {
            return $discount;
        }

        return $gross == 0.0 ? 0.0 : round(abs(($discount / $gross) * 100), 6);
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

    private function languageCode(string $language): string
    {
        $normalized = mb_strtolower(trim($language));

        if (preg_match('/^[a-z]{2}(?:[-_][a-z]{2})?$/', $normalized)) {
            return substr($normalized, 0, 2);
        }

        return match ($normalized) {
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

    /** @param array<string, string> $row */
    private function clientName(array $row): string
    {
        return $this->value($row, ['company'])
            ?: $this->fullName($row)
            ?: ($this->emailAddresses($row)[0] ?? '');
    }

    /**
     * @param array<string, string> $row
     * @return array<int, string>
     */
    private function emailAddresses(array $row): array
    {
        $values = preg_split('/\s*[,;\r\n]+\s*/u', $this->value($row, ['email'])) ?: [];
        $emails = [];

        foreach ($values as $value) {
            $email = $this->trim($value);
            $email_key = $this->key($email);

            if ($email !== '' && ! in_array($email_key, self::IGNORED_EMAIL_ADDRESSES, true)) {
                $emails[$email_key] = $email;
            }
        }

        return array_values($emails);
    }

    /** @param array<string, string> $row */
    private function fullName(array $row): string
    {
        return trim(implode(' ', array_filter([
            $this->value($row, ['first name']),
            $this->value($row, ['last name']),
        ])));
    }

    /** @param array<string, string> $row */
    private function value(array $row, array $headers): string
    {
        foreach ($headers as $header) {
            if (isset($row[$header]) && $row[$header] !== '') {
                return $this->trim($row[$header]);
            }
        }

        return '';
    }

    /** @param array<string, string> $row */
    private function matchingValue(array $row, string $pattern): string
    {
        [, $value] = $this->matchingEntry($row, $pattern);

        return $value;
    }

    /**
     * @param array<string, string> $row
     * @return array{0: string, 1: string}
     */
    private function matchingEntry(array $row, string $pattern): array
    {
        foreach ($row as $header => $value) {
            if (preg_match($pattern, $header)) {
                return [$header, $this->trim($value)];
            }
        }

        return ['', ''];
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

    private function boolean(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'paid', 'active'], true);
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
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function mergeMissing(array $existing, array $candidate): array
    {
        foreach ($candidate as $key => $value) {
            if ($key === 'contacts') {
                continue;
            }

            if (! array_key_exists($key, $existing) || $existing[$key] === '') {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, array{entity: string, key: string, name?: string}> $references
     * @param array<int|string, string> $aliases
     * @return array<string, mixed>
     */
    private function record(
        string $key,
        string $label,
        array $payload,
        array $references = [],
        array $aliases = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'payload' => $payload,
            'references' => $references,
            'aliases' => $aliases,
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

    private function key(string $value): string
    {
        return mb_strtolower($this->trim($value));
    }

    private function trim(string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $value) ?? trim($value);
    }
}

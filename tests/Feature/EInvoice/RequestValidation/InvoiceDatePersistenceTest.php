<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\DataProvider;

class InvoiceDatePersistenceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const ORIGINAL_E_INVOICE = ['Invoice' => [
        'InvoicePeriod' => [['StartDate' => '2026-09-01', 'EndDate' => '2026-09-30']],
        'Delivery' => [['ActualDeliveryDate' => '2026-09-30']],
    ]];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->makeTestData();

        $this->invoice->date = '2026-11-11';
        $this->invoice->due_date = '2026-12-11';
        $this->invoice->public_notes = 'Original notes';
        $this->invoice->e_invoice = self::ORIGINAL_E_INVOICE;
        $this->invoice->saveQuietly();

        $this->recurring_invoice->public_notes = 'Original notes';
        $this->recurring_invoice->e_invoice = self::ORIGINAL_E_INVOICE;
        $this->recurring_invoice->saveQuietly();
    }

    #[DataProvider('invoiceDateUpdates')]
    public function testInvoiceUpdateValidatesDatesBeforePersistence(
        array $changes,
        bool $accepted,
        ?array $expected_e_invoice,
        ?string $expected_error = null,
        string $resource = 'invoices'
    ): void
    {
        $invoice = $resource === 'recurring_invoices' ? $this->recurring_invoice : $this->invoice;
        $original_attributes = $invoice->fresh()->getRawOriginal();
        if ($resource === 'recurring_invoices') {
            $changes['next_send_date'] = now()->addDay()->format('Y-m-d');
        }

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/' . $resource . '/' . $invoice->hashed_id, $changes + [
            'public_notes' => 'Updated notes',
        ]);

        $stored_invoice = $invoice->fresh();

        if (!$accepted) {
            $response->assertStatus(422);
            $response->assertJsonValidationErrors($expected_error);
            $this->assertSame([$expected_error], array_keys($response->json('errors')));
            $this->assertSame($original_attributes, $stored_invoice->getRawOriginal());

            return;
        }

        $response->assertStatus(200);
        $response->assertJsonPath('data.public_notes', 'Updated notes');
        $this->assertSame('Updated notes', $stored_invoice->public_notes);
        $this->assertSame($expected_e_invoice, json_decode($stored_invoice->getRawOriginal('e_invoice') ?? 'null', true));
    }

    public static function invoiceDateUpdates(): iterable
    {
        // Each state contains the request value and the independently expected stored value.
        $start_dates = [
            'omitted' => [[], []],
            'null' => [['StartDate' => null], []],
            'earlier' => [['StartDate' => '2026-10-01'], ['StartDate' => '2026-10-01']],
            'later' => [['StartDate' => '2026-10-31'], ['StartDate' => '2026-10-31']],
            'invalid' => [['StartDate' => 'invalid'], []],
        ];
        $end_dates = [
            'omitted' => [[], []],
            'null' => [['EndDate' => null], []],
            'earlier' => [['EndDate' => '2026-10-01'], ['EndDate' => '2026-10-01']],
            'later' => [['EndDate' => '2026-10-31'], ['EndDate' => '2026-10-31']],
            'invalid' => [['EndDate' => 'invalid'], []],
        ];
        $delivery_dates = [
            'omitted' => [[], []],
            'null' => [['ActualDeliveryDate' => null], []],
            'valid' => [['ActualDeliveryDate' => '2026-11-05'], ['ActualDeliveryDate' => '2026-11-05']],
            'invalid' => [['ActualDeliveryDate' => 'invalid'], []],
        ];

        foreach ($start_dates as $start_state => [$start, $stored_start]) {
            foreach ($end_dates as $end_state => [$end, $stored_end]) {
                foreach ($delivery_dates as $delivery_state => [$delivery, $stored_delivery]) {
                    $accepted = $start_state !== 'invalid'
                        && $end_state !== 'invalid'
                        && $delivery_state !== 'invalid'
                        && !($start_state === 'later' && $end_state === 'earlier');
                    $expected_error = match (true) {
                        $start_state === 'invalid' => 'e_invoice.InvoicePeriod.0.StartDate',
                        $end_state === 'invalid' => 'e_invoice.InvoicePeriod.0.EndDate',
                        $delivery_state === 'invalid' => 'e_invoice.Invoice.Delivery.0.ActualDeliveryDate',
                        !$accepted => 'e_invoice.InvoicePeriod.0.EndDate',
                        default => null,
                    };

                    yield "start {$start_state}, end {$end_state}, delivery {$delivery_state}" => [
                        ['e_invoice' => ['Invoice' => ['InvoicePeriod' => [$start + $end], 'Delivery' => [$delivery]]]],
                        $accepted,
                        ['Invoice' => ['InvoicePeriod' => [$stored_start + $stored_end], 'Delivery' => [$stored_delivery]]],
                        $expected_error,
                    ];
                }
            }
        }

        $invalid_dates = [
            'calendar overflow' => '2026-02-30',
            'non-leap February 29' => '2026-02-29',
            'invalid month' => '2026-13-01',
            'non-padded month' => '2026-2-03',
            'non-padded day' => '2026-02-3',
            'timestamp' => '2026-10-01T12:00:00+00:00',
            'relative expression' => 'first day of this month',
            'integer' => 20261001,
            'zero' => 0,
            'boolean' => false,
            'array' => [],
            'serialized datetime' => ['date' => '2026-10-01 00:00:00.000000', 'timezone_type' => 3, 'timezone' => 'UTC'],
        ];

        foreach ($invalid_dates as $label => $date) {
            foreach (['StartDate', 'EndDate', 'ActualDeliveryDate'] as $field) {
                $invoice = ['InvoicePeriod' => [['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31']]];
                if ($field === 'ActualDeliveryDate') {
                    $invoice['Delivery'] = [[$field => $date]];
                } else {
                    $invoice['InvoicePeriod'][0][$field] = $date;
                }

                $expected_error = $field === 'ActualDeliveryDate'
                    ? 'e_invoice.Invoice.Delivery.0.ActualDeliveryDate'
                    : "e_invoice.InvoicePeriod.0.{$field}";
                yield "{$field}: {$label}" => [['e_invoice' => ['Invoice' => $invoice]], false, null, $expected_error];
            }
        }

        foreach ([
            'empty string' => ['', null],
            'whitespace' => [' ', null],
            'trimmed date' => [' 2026-10-01 ', '2026-10-01'],
        ] as $label => [$date, $stored_date]) {
            foreach (['StartDate', 'EndDate', 'ActualDeliveryDate'] as $field) {
                $invoice = ['InvoicePeriod' => [['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31']]];
                $expected_invoice = $invoice;

                if ($field === 'ActualDeliveryDate') {
                    $invoice['Delivery'] = [[$field => $date]];
                    $expected_invoice['Delivery'] = [$stored_date === null ? [] : [$field => $stored_date]];
                } else {
                    $invoice['InvoicePeriod'][0][$field] = $date;
                    if ($stored_date === null) {
                        unset($expected_invoice['InvoicePeriod'][0][$field]);
                    } else {
                        $expected_invoice['InvoicePeriod'][0][$field] = $stored_date;
                    }
                }

                yield "{$field}: {$label}" => [
                    ['e_invoice' => ['Invoice' => $invoice]], true, ['Invoice' => $expected_invoice],
                ];
            }
        }

        yield 'omitted e-invoice preserves stored dates' => [[], true, self::ORIGINAL_E_INVOICE];
        yield 'null e-invoice clears stored data' => [['e_invoice' => null], true, null];
        yield 'empty e-invoice replaces stored data' => [['e_invoice' => []], true, []];
        yield 'empty invoice replaces stored dates' => [['e_invoice' => ['Invoice' => []]], true, ['Invoice' => []]];
        yield 'empty period and delivery lists' => [
            ['e_invoice' => ['Invoice' => ['InvoicePeriod' => [], 'Delivery' => []]]],
            true,
            ['Invoice' => ['InvoicePeriod' => [], 'Delivery' => []]],
        ];
        yield 'leap-day period and delivery' => [
            ['e_invoice' => ['Invoice' => [
                'InvoicePeriod' => [['StartDate' => '2028-02-29', 'EndDate' => '2028-02-29']],
                'Delivery' => [['ActualDeliveryDate' => '2028-02-29']],
            ]]],
            true,
            ['Invoice' => [
                'InvoicePeriod' => [['StartDate' => '2028-02-29', 'EndDate' => '2028-02-29']],
                'Delivery' => [['ActualDeliveryDate' => '2028-02-29']],
            ]],
        ];

        $valid_period = ['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31'];
        foreach ([
            'reversed second period' => [
                ['InvoicePeriod' => [$valid_period, ['StartDate' => '2026-10-31', 'EndDate' => '2026-10-01']]],
                'e_invoice.InvoicePeriod.1.EndDate',
            ],
            'invalid second period' => [
                ['InvoicePeriod' => [$valid_period, ['EndDate' => '2026-02-30']]],
                'e_invoice.InvoicePeriod.1.EndDate',
            ],
            'invalid second delivery' => [
                ['Delivery' => [['ActualDeliveryDate' => '2026-10-31'], ['ActualDeliveryDate' => '2026-02-30']]],
                'e_invoice.Invoice.Delivery.1.ActualDeliveryDate',
            ],
            'invalid delivery with a valid period' => [
                ['InvoicePeriod' => [$valid_period], 'Delivery' => [['ActualDeliveryDate' => 'invalid']]],
                'e_invoice.Invoice.Delivery.0.ActualDeliveryDate',
            ],
        ] as $label => [$invoice, $expected_error]) {
            yield $label => [['e_invoice' => ['Invoice' => $invoice]], false, null, $expected_error];
        }

        foreach (['invoices', 'recurring_invoices'] as $resource) {
            $e_invoice = ['Invoice' => ['InvoicePeriod' => [[
                'Description' => 'first day of this month|last day of this month',
            ]]]];
            yield "{$resource}: valid relative period" => [['e_invoice' => $e_invoice], true, $e_invoice, null, $resource];

            foreach ([
                'missing pair' => ['first day of this month', 'StartDate'],
                'invalid start' => ['not-a-date|tomorrow', 'StartDate'],
                'invalid end' => ['today|not-a-date', 'EndDate'],
                'extra separator' => ['today|tomorrow|yesterday', 'StartDate'],
            ] as $label => [$description, $date_field]) {
                foreach ([0, 1] as $period_index) {
                    $periods = [['Description' => 'today|tomorrow']];
                    $periods[$period_index] = ['Description' => $description];
                    yield "{$resource}: {$label}, period {$period_index}" => [
                        ['e_invoice' => ['Invoice' => ['InvoicePeriod' => $periods]]],
                        false,
                        null,
                        "e_invoice.InvoicePeriod.Description.{$period_index}.{$date_field}",
                        $resource,
                    ];
                }
            }
        }
    }
}

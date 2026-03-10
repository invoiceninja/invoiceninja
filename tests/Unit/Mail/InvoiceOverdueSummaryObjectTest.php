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

namespace Tests\Unit\Mail;

use App\Mail\Admin\InvoiceOverdueSummaryObject;
use App\Models\Invoice;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceOverdueSummaryObjectTest extends TestCase
{
    use DatabaseTransactions;
    use MakesDates;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * Overdue invoice row shape matches InvoiceCheckOverdue (id, client, number, amount, due_date, formatted_*, ...).
     */
    private function buildOverdueInvoicesPayload(Invoice $invoice): array
    {
        $client = $invoice->client;
        $company = $invoice->company;

        return [
            'id' => $invoice->id,
            'client' => $client->present()->name(),
            'number' => $invoice->number,
            'amount' => max($invoice->partial ?? 0, $invoice->balance),
            'due_date' => $invoice->due_date,
            'formatted_amount' => Number::formatMoney($invoice->balance, $client),
            'formatted_due_date' => $this->translateDate($invoice->due_date, $company->date_format(), $company->locale()),
        ];
    }

    /**
     * Table headers match those used in InvoiceCheckOverdue::sendOverdueNotifications.
     */
    private function buildTableHeaders(): array
    {
        return [
            'client' => ctrans('texts.client'),
            'number' => ctrans('texts.invoice_number'),
            'formatted_due_date' => ctrans('texts.due_date'),
            'formatted_amount' => ctrans('texts.amount'),
        ];
    }

    public function test_table_rows_have_exactly_table_headers_keys_in_same_order(): void
    {
        $overdue_invoices = [
            $this->buildOverdueInvoicesPayload($this->invoice),
            $this->buildOverdueInvoicesPayload($this->invoice),
        ];
        $table_headers = $this->buildTableHeaders();
        $expected_keys = array_keys($table_headers);

        $obj = new InvoiceOverdueSummaryObject($overdue_invoices, $table_headers, $this->company, true);
        $mail = $obj->build();

        $table = $mail->data['table'];
        $this->assertCount(2, $table, 'Table should have two rows');

        foreach ($table as $index => $row) {
            $row_keys = array_keys($row);
            $this->assertSame($expected_keys, $row_keys, "Row {$index} keys should match table_headers keys in same order");
            $this->assertArrayNotHasKey('id', $row, "Row {$index} must not contain id");
            $this->assertArrayNotHasKey('due_date', $row, "Row {$index} must not contain due_date");
            $this->assertArrayNotHasKey('amount', $row, "Row {$index} must not contain amount");
        }
    }

    public function test_table_row_values_match_incoming_overdue_data_for_header_keys(): void
    {
        $payload = $this->buildOverdueInvoicesPayload($this->invoice);
        $overdue_invoices = [$payload];
        $table_headers = $this->buildTableHeaders();

        $obj = new InvoiceOverdueSummaryObject($overdue_invoices, $table_headers, $this->company, true);
        $mail = $obj->build();

        $row = $mail->data['table'][0];
        foreach (array_keys($table_headers) as $key) {
            $this->assertArrayHasKey($key, $row);
            $this->assertSame($payload[$key], $row[$key], "Row value for '{$key}' should match incoming overdue_invoices value");
        }
    }
}

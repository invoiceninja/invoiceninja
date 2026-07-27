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

namespace Tests\Feature\Export;

use App\Export\CSV\InvoiceExport;
use App\Models\Invoice;
use App\Models\Location;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use League\Csv\Reader;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceLocationExportTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceExportResolvesLocationFields(): void
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'name' => 'Warehouse A',
            'address1' => '500 Export Lane',
            'city' => 'Seattle',
            'state' => 'WA',
            'postal_code' => '98101',
            'is_shipping_location' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'location_id' => $location->id,
            'number' => 'INV-LOC-001',
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'is_deleted' => false,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => [
                'invoice.number',
                'client.name',
                'location.name',
                'location.address1',
                'location.city',
                'location.state',
                'location.postal_code',
                'location.is_shipping_location',
            ],
            'send_email' => false,
            'include_deleted' => false,
            'client_id' => $this->client->hashed_id,
            'status' => null,
        ]);

        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = collect(iterator_to_array($reader->getRecords()))
            ->filter(fn ($row) => ($row['Invoice Invoice Number'] ?? '') === $invoice->number)
            ->values();

        $this->assertCount(1, $records);

        $row = $records->first();
        $this->assertEquals('INV-LOC-001', $row['Invoice Invoice Number']);
        $this->assertEquals($this->client->name, $row['Client Name']);
        $this->assertEquals('Warehouse A', $row['Location Name']);
        $this->assertEquals('500 Export Lane', $row['Location Street']);
        $this->assertEquals('Seattle', $row['Location City']);
        $this->assertEquals('WA', $row['Location State/Province']);
        $this->assertEquals('98101', $row['Location Postal Code']);
        $this->assertEquals(ctrans('texts.yes'), $row['Location Is Shipping']);
    }

    public function testInvoiceExportWithoutLocationReturnsEmptyLocationFields(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'location_id' => null,
            'number' => 'INV-NO-LOC',
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'is_deleted' => false,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => [
                'invoice.number',
                'location.name',
                'location.city',
            ],
            'send_email' => false,
            'include_deleted' => false,
            'client_id' => $this->client->hashed_id,
            'status' => null,
        ]);

        $csv = $export->run();

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = collect(iterator_to_array($reader->getRecords()))
            ->filter(fn ($row) => ($row['Invoice Invoice Number'] ?? '') === $invoice->number)
            ->values();

        $this->assertCount(1, $records);

        $row = $records->first();
        $this->assertEquals('INV-NO-LOC', $row['Invoice Invoice Number']);
        $this->assertEmpty($row['Location Name']);
        $this->assertEmpty($row['Location City']);
    }

    public function testInvoiceExportJsonIncludesLocationColumns(): void
    {
        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'name' => 'JSON Location',
            'city' => 'Portland',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'location_id' => $location->id,
            'number' => 'INV-JSON-LOC',
            'status_id' => Invoice::STATUS_SENT,
            'date' => now()->format('Y-m-d'),
            'is_deleted' => false,
        ]);

        $export = new InvoiceExport($this->company, [
            'date_range' => 'all',
            'report_keys' => [
                'invoice.number',
                'location.name',
                'location.city',
            ],
            'send_email' => false,
            'include_deleted' => false,
            'client_id' => $this->client->hashed_id,
            'status' => null,
        ]);

        $json = $export->returnJson();

        $this->assertArrayHasKey('columns', $json);

        $identifiers = collect($json['columns'])->pluck('identifier')->all();
        $this->assertContains('location.name', $identifiers);
        $this->assertContains('location.city', $identifiers);

        $rows = array_values(array_filter($json, fn ($key) => is_int($key), ARRAY_FILTER_USE_KEY));
        $matched = collect($rows)->first(function ($row) use ($invoice) {
            $number = collect($row)->firstWhere('identifier', 'invoice.number');

            return ($number['value'] ?? null) === $invoice->number;
        });

        $this->assertNotNull($matched);

        $name_cell = collect($matched)->firstWhere('identifier', 'location.name');
        $city_cell = collect($matched)->firstWhere('identifier', 'location.city');

        $this->assertEquals('JSON Location', $name_cell['value'] ?? null);
        $this->assertEquals('Portland', $city_cell['value'] ?? null);
    }
}

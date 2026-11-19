<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Export\CSV\ClientExport;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 */
class ClientCsvTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testRecurringInvoiceExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/recurring_invoices', $data);

        $response->assertStatus(200);
    }

    public function testVendorExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/vendors', $data);

        $response->assertStatus(200);
    }

    public function testPurchaseOrderExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/purchase_orders', $data);

        $response->assertStatus(200);
    }

    public function testClientExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/clients', $data);

        $response->assertStatus(200);
    }

    public function testClientExportFiltersByTheRequestedDateKey()
    {
        DB::table('clients')->where('id', $this->client->id)->update([
            'created_at' => now()->subYears(5),
            'updated_at' => now()->subDays(3),
        ]);

        $export = new ClientExport($this->company, [
            'date_range' => 'custom',
            'start_date' => now()->subDays(7)->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'date_key' => 'updated_at',
            'include_deleted' => false,
            'report_keys' => ['client.name'],
            'send_email' => false,
            'user_id' => $this->user->id,
        ]);

        $reader = Reader::fromString($export->run());
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords());

        $this->assertCount(1, $records);
        $this->assertEquals($this->client->name, reset($records)['Name']);
    }

    public function testContactExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/contacts', $data);

        $response->assertStatus(200);
    }

    public function testActivityExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/activities', $data);

        $response->assertStatus(200);
    }
}

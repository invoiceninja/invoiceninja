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

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ClientCsvTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testClientExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/clients', $data);

        $response->assertStatus(200);
    }

    public function testContactExportCsv()
    {
        $data = [
            'date_range' => 'this_year',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/contacts', $data);

        $response->assertStatus(200);
    }
}

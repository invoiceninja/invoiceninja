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

use Tests\TestCase;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Export\CSV\CreditExport;
use App\Jobs\Report\PreviewReport;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 */
class ReportPreviewTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();

    }

    public function testCreditExportPreview()
    {
        
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $p = (new PreviewReport($this->company, $data, CreditExport::class, '123'))->handle();

        $this->assertNull($p);

    }

    public function testCreditPreview()
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/credits?output=json', $data)
        ->assertStatus(200);

    }
}
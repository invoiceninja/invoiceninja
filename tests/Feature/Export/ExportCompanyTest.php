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

use App\Jobs\Company\CompanyExport;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class ExportCompanyTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        // $this->faker = \Faker\Factory::create();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        if (!config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Cannot write to TMP - skipping');
        }
    }

    public function testCompanyExport()
    {
        $res = (new CompanyExport($this->company, $this->company->users->first(), '123'))->handle();

        $this->assertTrue($res);
    }
}

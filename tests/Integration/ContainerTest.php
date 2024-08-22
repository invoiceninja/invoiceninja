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

namespace Tests\Integration;

use App\Models\Company;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class ContainerTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        app()->instance(Company::class, $this->company);
    }

    public function testBindingWorks()
    {
        $resolved_company = resolve(Company::class);

        $this->assertNotNull($resolved_company);

        $this->assertEquals($this->account->id, $resolved_company->account_id);
    }
}

<?php

namespace Tests\Unit\Migration;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testImportClassExists()
    {
        $status = class_exists('App\Jobs\Util\Import');

        $this->assertTrue($status);
    }

    /**
     * Ensure exception is thrown when resource
     * is not available for the migration.
     */
    public function testExceptionOnUnavailableResource()
    {
        $data['panda_bears'] = [
            'name' => 'Awesome Panda Bear',
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->expectException(ResourceNotAvailableForMigration::class);
    }

    public function testCompanyUpdating()
    {
        $original_company_key = $this->company->company_key;

        $data['company'] = [
            'company_key' => 0,
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($original_company_key, $this->company->company_key);
    }

    public function testTaxRatesInserting()
    {
        $total_tax_rates = TaxRate::count();

        print $total_tax_rates;

        $data['tax_rates'] = [
            0 => [
                'name' => 'My awesome tax rate 1',
                'rate' => '1.000',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($total_tax_rates, TaxRate::count());
    }
}

<?php

namespace Tests\Unit\Migration;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
use App\Models\Client;
use App\Models\TaxRate;
use App\Models\User;
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

        $data['tax_rates'] = [
            0 => [
                'name' => 'My awesome tax rate 1',
                'rate' => '1.000',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($total_tax_rates, TaxRate::count());
    }

    public function testTaxRateUniqueValidation()
    {
        $original_number = TaxRate::count();

        $data['tax_rates'] = [
            0 => [
                'name' => 'My awesome tax rate 1',
                'rate' => '1.000',
            ],
            1 => [
                'name' => 'My awesome tax rate 1',
                'rate' => '1.000',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->expectException(\Exception::class);
        $this->assertEquals($original_number, TaxRate::count());
    }

    public function testUsersImporting()
    {
        $original_number = User::count();

        $data['users'] = [
            0 => [
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'IN',
                'email' => 'my@awesomemail.com',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, User::count());
    }

    public function testUserValidator()
    {
        $original_number = User::count();

        $data['users'] = [
            0 => [
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'IN',
                'email' => 'my@awesomemail.com',
            ],
            1 => [
                'id' => 2,
                'first_name' => 'Someone',
                'last_name' => 'Else',
                'email' => 'my@awesomemail.com',
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->expectException(\Exception::class);
        $this->assertEquals($original_number, User::count());
    }

    public function testClientImportingDependsOnUsers()
    {
        $data['clients'] = [
            0 => [
                'name' => 'My client',
                'balance' => '0.00',
                'user_id' => 1,
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);
    }

    public function testClientImporting()
    {
        $original_number = Client::count();

        print $original_number;

        $data['users'] = [
            0 => [
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'IN',
                'email' => 'my@awesomemail.com',
            ],
            1 => [
                'id' => 2,
                'first_name' => 'Someone',
                'last_name' => 'Else',
                'email' => 'my@awesomemail2.com',
            ]
        ];

        $data['clients'] = [
            0 => [
                'id' => 1,
                'name' => 'My awesome client',
                'balance' => '0.00',
                'user_id' => 1,
            ]
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertGreaterThan($original_number, Client::count());
    }
}

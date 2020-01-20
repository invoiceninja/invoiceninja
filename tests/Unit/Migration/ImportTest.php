<?php

namespace Tests\Unit\Migration;

use App\Exceptions\ResourceNotAvailableForMigration;
use App\Jobs\Util\Import;
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
        $original_key = $this->company->company_key;

        $data['company'] = [
            'company_key' => 'my_awesome_secret_key',
        ];

        Import::dispatchNow($data, $this->company, $this->user);

        $this->assertNotEquals($this->company->company_key, $original_key);
    }
}

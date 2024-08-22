<?php

namespace Tests\Unit\Services\Import\Quickbooks;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Collection;
use App\Services\Import\Quickbooks\Service as QuickbooksService;
use App\Services\Import\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;

class ServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped("no bueno");
        // Inject the mock into the IntuitSDKservice instance
        $this->service = Mockery::mock(new QuickbooksService(Mockery::mock(QuickbooksInterface::class)))->shouldAllowMockingProtectedMethods();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testTotalRecords()
    {
        $entity = 'Customer';
        $count = 10;

        $this->service->shouldReceive('totalRecords')
                      ->with($entity)
                      ->andReturn($count);

        $result = $this->service->totalRecords($entity);

        $this->assertEquals($count, $result);
    }

    public function testHasFetchRecords()
    {
        $entity = 'Customer';
        $count = 10;

        $this->service->shouldReceive('fetchRecords')
                      ->with($entity, $count)
                      ->andReturn(collect());

        $result = $this->service->fetchCustomers($count);

        $this->assertInstanceOf(Collection::class, $result);
    }
}

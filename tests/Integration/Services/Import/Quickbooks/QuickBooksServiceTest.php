<?php

namespace Tests\Integration\Services\Import\Quickbooks;

use App\Services\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;
use App\Services\Quickbooks\Service as QuickbooksService;
use App\Services\Quickbooks\SdkWrapper as QuickbooksSDK;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Tests\TestCase;
use Mockery;

class QuickBooksServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped("no bueno");
        $data = json_decode(
            file_get_contents(base_path('tests/Mock/Quickbooks/Data/customers.json')),
            true
        );
        $count = count($data);
        $sdkMock = Mockery::mock(\stdClass::class);
        $sdkMock->shouldReceive('Query')->andReturnUsing(function ($val) use ($count, $data) {
            if(stristr($val, 'count')) {
                return $count;
            }

            return Arr::take($data, 4);
        });
        app()->singleton(QuickbooksInterface::class, fn () => new QuickbooksSDK($sdkMock));

        $this->service = app(QuickbooksService::class);

    }

    public function testImportCustomers()
    {
        $collection = $this->service->fetchCustomers(4);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertEquals(4, $collection->count());
        $this->assertNotNull($item = $collection->whereStrict('CompanyName', "Cool Cars")->first());
        $this->assertEquals("Grace", $item['GivenName']);
    }
}

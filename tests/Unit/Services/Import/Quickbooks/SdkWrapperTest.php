<?php

// tests/Unit/IntuitSDKWrapperTest.php

namespace Tests\Unit\Services\Import\Quickbooks;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Arr;
use App\Services\Quickbooks\Contracts\SdkInterface;
use App\Services\Quickbooks\SdkWrapper as QuickbookSDK;

class SdkWrapperTest extends TestCase
{
    protected $sdk;
    protected $sdkMock;

    protected function setUp(): void
    {
        parent::setUp();


        $this->markTestSkipped("no bueno");

        $this->sdkMock = Mockery::mock(\stdClass::class);
        $this->sdk = new QuickbookSDK($this->sdkMock);

        $this->markTestSkipped('no resource');
    }

    public function testIsInstanceOf()
    {
        $this->assertInstanceOf(SdkInterface::class, $this->sdk);
    }

    public function testMethodFetchRecords()
    {
        $data = json_decode(
            file_get_contents(base_path('tests/Mock/Quickbooks/Data/customers.json')),
            true
        );
        $count = count($data);
        $this->sdkMock->shouldReceive('Query')->andReturnUsing(function ($val) use ($count, $data) {
            if(stristr($val, 'count')) {
                return $count;
            }

            return Arr::take($data, 4);
        });

        $this->assertEquals($count, $this->sdk->totalRecords('Customer'));
        $this->assertEquals(4, count($this->sdk->fetchRecords('Customer', 4)));
    }
}

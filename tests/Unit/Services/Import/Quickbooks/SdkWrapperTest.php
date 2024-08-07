<?php
// tests/Unit/IntuitSDKWrapperTest.php
namespace Tests\Unit\Services\Import\Quickbooks;

use Mockery;
use Tests\TestCase;
use Illuminate\Support\Arr;
use App\Services\Import\Quickbooks\Contracts\SdkInterface;
use App\Services\Import\Quickbooks\SdkWrapper as QuickbookSDK;

class SdkWrapperTest extends TestCase
{

    protected $sdk;
    protected $sdkMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sdkMock = Mockery::mock(sdtClass::class);
        $this->sdk = new QuickbookSDK($this->sdkMock);


    }

    function testIsInstanceOf() {
        $this->assertInstanceOf(SdkInterface::class, $this->sdk);
    }

    function testMethodFetchRecords() {
        $data = json_decode(
                    file_get_contents(base_path('tests/Mock/Quickbooks/Data/customers.json')),true
        );
        $count = count($data);
        $this->sdkMock->shouldReceive('Query')->andReturnUsing(function($val) use ($count, $data) {
            if(stristr($val, 'count')) {
                return $count;
            }

            return Arr::take($data,4);
        });

        $this->assertEquals($count, $this->sdk->totalRecords('Customer'));
        $this->assertEquals(4, count($this->sdk->fetchRecords('Customer', 4)));
    }
}

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

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use Tests\MockAccountData;
use Http\Message\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\Invoice\EInvoice\FacturaEInvoice;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use function Amp\Iterator\toArray;

/**
 * @test
 */
class FacturaeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testInvoiceGeneration()
    {

        $f = new FacturaEInvoice($this->invoice, "3.2.2");
        $path = $f->run();

        $this->assertNotNull($f->run());
        
        nlog($f->run());

        // $this->assertTrue($this->validateInvoiceXML($path));
    }


//     private function validateInvoiceXML($path) {



//     $jar = (new \GuzzleHttp\Cookie\CookieJar())->toArray();

// echo print_r($jar);

//     $response = Http::withCookies($jar, '.ninja.test')->attach(
//         'xmlFile',
//         Storage::get($path),
//         basename($path)
//     )->post('https://viewer.facturadirecta.com/dp/viewer/upload.void'); // Instance of Guzzle/CookieJar

//     echo print_r($jar);

//     $response = Http::withCookies($jar, '.ninja.test')->post('https://viewer.facturadirecta.com/dp/viewer/viewer.void');
//     echo print_r($response->body(), 1);

// }

}
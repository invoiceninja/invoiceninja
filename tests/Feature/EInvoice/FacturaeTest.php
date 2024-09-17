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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class FacturaeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testInvoiceGeneration()
    {

        $f = new \App\Services\EDocument\Standards\FacturaEInvoice($this->invoice, "3.2.2");
        $path = $f->run();

        $this->assertNotNull($f->run());

        // nlog($f->run());

        // $this->assertTrue($this->validateInvoiceXML($path));
    }


    // protected function validateInvoiceXML($path, $validateSignature=false) {
    //     // Prepare file to upload
    //     if (function_exists('curl_file_create')) {
    //       $postFile = curl_file_create($path);
    //     } else {
    //       $postFile = "@" . realpath($path);
    //     }

    //     // Send upload request
    //     $ch = curl_init();
    //     curl_setopt_array($ch, array(
    //       CURLOPT_RETURNTRANSFER => true,
    //       CURLOPT_FOLLOWLOCATION => true,
    //       CURLOPT_URL => "http://plataforma.firma-e.com/VisualizadorFacturae/index2.jsp",
    //       CURLOPT_POST => 1,
    //       CURLOPT_POSTFIELDS => array(
    //         "referencia" => $postFile,
    //         "valContable" => "on",
    //         "valFirma" => $validateSignature ? "on" : "off",
    //         "aceptarCondiciones" => "on",
    //         "submit" => "Siguiente"
    //       ),
    //       CURLOPT_COOKIEJAR => base_path()."/cookie.txt"
    //     ));
    //     $res = curl_exec($ch);
    //     curl_close($ch);
    //     unset($ch);

    // nlog($res);

    //     if (strpos($res, "window.open('facturae.jsp'") === false) {
    //       $this->expectException(\UnexpectedValueException::class);
    //     }

    //     // Fetch results
    //     $ch = curl_init();
    //     curl_setopt_array($ch, array(
    //       CURLOPT_RETURNTRANSFER => true,
    //       CURLOPT_FOLLOWLOCATION => true,
    //       CURLOPT_URL => "http://plataforma.firma-e.com/VisualizadorFacturae/facturae.jsp",
    //       CURLOPT_COOKIEFILE => base_path()."/cookie.txt"
    //     ));
    //     $res = curl_exec($ch);
    //     curl_close($ch);
    //     unset($ch);

    // nlog($res);

    //     // Validate results
    //     $this->assertNotEmpty($res, 'Invalid Validator Response');
    //     $this->assertNotEmpty(strpos($res, 'euro_ok.png'), 'Invalid XML Format');
    //     if ($validateSignature) {
    //       $this->assertNotEmpty(strpos($res, '>Nivel de Firma Válido<'), 'Invalid Signature');
    //     }
    //     if (strpos($res, '>Sellos de Tiempo<') !== false) {
    //       $this->assertNotEmpty(strpos($res, '>XAdES_T<'), 'Invalid Timestamp');
    //     }
    //   }

    // private function validateInvoiceXML($path)
    // {
    //     $client = new \GuzzleHttp\Client(['cookies' => true]);

    //     $response = $client->request('POST', 'https://face.gob.es/api/v1/herramientas/validador',[
    //         'multipart' => [
    //             [
    //                 'name'     => 'validador[factura]',
    //                 'contents' => Storage::get($path),
    //             ],
    //         ]
    //     ]);

    //     $response = $client->request('POST', 'http://plataforma.firma-e.com/VisualizadorFacturae/facturae.jsp');
    //     $body = $response->getBody();
    //     $stringBody = (string) $body;

    //     echo print_r($stringBody,1);


    // }

}

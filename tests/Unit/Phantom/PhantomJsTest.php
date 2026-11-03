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

namespace Tests\Unit\Phantom;

use App\Utils\PhantomJS\Phantom;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 *
 *   App\Utils\PhantomJS\Phantom
 */
class PhantomJsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testValidPdfMime()
    {
        $pdf = file_get_contents(base_path('/tests/Unit/Phantom/valid.pdf'));

        $finfo = new \finfo(FILEINFO_MIME);

        $this->assertEquals('application/pdf; charset=binary', $finfo->buffer($pdf));
    }

    public function testInValidPdfMime()
    {
        $pdf = file_get_contents(base_path('/tests/Unit/Phantom/invalid.pdf'));

        $finfo = new \finfo(FILEINFO_MIME);

        $this->assertNotEquals('application/pdf; charset=binary', $finfo->buffer($pdf));
    }

    public function testConvertHtmlToPdfPostsExpectedPayloadToPhantomJsCloud(): void
    {
        config(['ninja.phantomjs_key' => 'test-key']);

        $pdf_bytes = file_get_contents(base_path('tests/Unit/Phantom/valid.pdf'));

        Http::fake([
            'phantomjscloud.com/*' => Http::response($pdf_bytes, 200),
        ]);

        $html = '<html><body>Invoice</body></html>';
        $response = (new Phantom())->convertHtmlToPdf($html);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame($pdf_bytes, $response->getContent());

        Http::assertSent(function ($request) use ($html) {
            $expected_body = json_encode([
                'content' => $html,
                'renderType' => 'pdf',
                'outputAsJson' => false,
                'renderSettings' => [
                    'emulateMedia' => 'print',
                    'pdfOptions' => [
                        'preferCSSPageSize' => true,
                        'printBackground' => true,
                    ],
                ],
            ]);

            return $request->url() === 'https://phantomjscloud.com/api/browser/v2/test-key/'
                && $request->method() === 'POST'
                && $request->body() === $expected_body
                && $request->header('Content-Type')[0] === 'application/x-www-form-urlencoded';
        });
    }
}

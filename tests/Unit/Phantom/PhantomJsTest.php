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
}

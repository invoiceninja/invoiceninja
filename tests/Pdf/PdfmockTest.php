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

namespace Tests\Pdf;

use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockAccountData;
use Beganovich\Snappdf\Snappdf;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;

/**
 * @test
 * @covers  App\Services\Pdf\PdfService
 */
class PdfmockTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

    }

    public function testPdfInstance ()
    {

        $entity = (new \App\Services\Pdf\PdfMock())->build();

        $this->assertInstanceOf(Invoice::class, $entity);
        $this->assertNotNull($entity->client);

    }
}

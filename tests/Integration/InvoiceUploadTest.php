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

namespace Tests\Integration;

use App\Jobs\Entity\CreateEntityPdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Invoice\GetInvoicePdf
 */
class InvoiceUploadTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceUploadWorks()
    {
        CreateEntityPdf::dispatchSync($this->invoice->invitations->first());

        $this->assertNotNull($this->invoice->service()->getInvoicePdf($this->invoice->client->primary_contact()->first()));
    }
}

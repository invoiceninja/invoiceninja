<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Jobs\Entity\CreateEntityPdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class PdfCreatorTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testCreditPdfCreated()
    {
        $credit_path = CreateEntityPdf::dispatchNow($this->credit->invitations->first());
    
        $this->assertTrue(Storage::exists($this->client->credit_filepath().$this->credit->number.'.pdf'));
    }

    public function testInvoicePdfCreated()
    {
        $invoice_path = CreateEntityPdf::dispatchNow($this->invoice->invitations->first());
    
        $this->assertTrue(Storage::exists($this->client->invoice_filepath().$this->invoice->number.'.pdf'));
    }

    public function testQuotePdfCreated()
    {
        $quote_path = CreateEntityPdf::dispatchNow($this->quote->invitations->first());
    
        $this->assertTrue(Storage::exists($this->client->quote_filepath().$this->quote->number.'.pdf'));
    }
}

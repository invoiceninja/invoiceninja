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

namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceSum
 */
class InvoiceStatusTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $invoice;

    public $invoice_calc;

    public $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testSentStatus()
    {
        $this->invoice->due_date = now()->addMonth();
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->assertEquals(Invoice::STATUS_UNPAID, $this->invoice->getStatusAttribute());
    }

    public function testPartialStatus()
    {
        $this->invoice->partial_due_date = now()->addMonth();
        $this->invoice->status_id = Invoice::STATUS_SENT;

        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->getStatusAttribute());
    }
}

<?php

namespace Tests\Unit;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class EntityTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $invoice;

    public $invoice_calc;

    public $settings;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->invoice->line_items = $this->buildLineItems();

        $this->invoice_calc = new InvoiceSum($this->invoice);
    }

    public function testEntityNameResolution()
    {
        $entity_type = $this->invoice->getEntityType();

        $this->assertEquals('Invoice', class_basename($entity_type));

        $invitation = $this->invoice->invitations->first();

        $entity_type = $invitation->getEntityType();

        $this->assertEquals('InvoiceInvitation', class_basename($entity_type));

        $this->assertEquals('InvoiceInvitation', class_basename($invitation));
    }
}

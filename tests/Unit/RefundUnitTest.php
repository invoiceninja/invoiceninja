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

use App\Helpers\Invoice\ProRata;
use App\Models\RecurringInvoice;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 
 */
class RefundUnitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    public function testDiffInDays()
    {
        $this->assertEquals(30, intval(abs(Carbon::parse('2021-01-01')->diffInDays(Carbon::parse('2021-01-31')))));
    }
}

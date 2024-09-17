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

use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class RelationExistsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private $models = [
        Invoice::class,
        Client::class,
        Expense::class,
        Credit::class,
        Payment::class,
        RecurringInvoice::class,
        RecurringExpense::class,
        Product::class,
        Quote::class,
        Task::class,
        Vendor::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAssignedUserRelationExists()
    {
        foreach ($this->models as $model) {
            $class = new $model();

            $this->assertTrue(method_exists($class, 'assigned_user'));
        }
    }
}

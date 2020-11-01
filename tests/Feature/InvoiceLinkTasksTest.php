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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Task;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class InvoiceLinkTasksTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testMapCreation()
    {
        $temp_invoice_id = $this->invoice->id;

        $tasks = collect($this->invoice->line_items)->map(function ($item){

            if(isset($item->task_id))
                $item->task_id = $this->decodePrimaryKey($item->task_id);

            if(isset($item->expense_id))
                $item->expense_id = $this->decodePrimaryKey($item->expense_id);

            return $item;

        });

        $this->assertEquals($tasks->first()->task_id, $this->task->id);
        $this->assertEquals($tasks->first()->expense_id, $this->expense->id);

        $this->invoice = $this->invoice->service()->linkEntities()->save();

        $this->assertEquals($this->task->fresh()->invoice_id, $temp_invoice_id);
        $this->assertEquals($this->expense->fresh()->invoice_id, $temp_invoice_id);
    }
}

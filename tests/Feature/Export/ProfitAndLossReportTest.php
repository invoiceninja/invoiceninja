<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature\Export;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\Report\ProfitLoss;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Report\ProfitLoss
 */
class ProfitAndLossReportTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();

        $this->buildData();
    }

    public $company;

    public $payload;

/**
 *
 *      start_date - Y-m-d
        end_date - Y-m-d
        date_range - 
            all
            last7
            last30
            this_month
            last_month
            this_quarter
            last_quarter
            this_year
            custom
        income_billed - true = Invoiced || false = Payments
        expense_billed - true = Expensed || false = Expenses marked as paid
        include_tax - true tax_included || false - tax_excluded

*/

    private function buildData()
    {
        $this->company = Company::factory()->create([
                'account_id' => $this->account->id,
            ]);

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'income_billed' => true,
            'include_tax' => false
        ];

    }

    public function testProfitLossInstance()
    {
 
        $pl = new ProfitLoss($this->company, $this->payload);

        $this->assertInstanceOf(ProfitLoss::class, $pl);

    }
}

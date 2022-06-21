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

namespace Tests\Unit\ValidationRules;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\ValidationRules\Account\BlackListRule;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class UniqueInvoiceNumberValidationTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testValidEmailRule()
    {
        auth()->login($this->user);
        auth()->user()->setCompany($this->company);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'paid_to_date' => 100,
            'status_id' => 4,
            'date' => now(),
            'due_date'=> now(),
            'number' => 'db_record',
        ]);

        $data = [
            'client_id' => $this->client->hashed_id,
            'paid_to_date' => 100,
            'status_id' => 4,
            'date' => now(),
            'due_date'=> now(),
            'number' => 'db_record',
        ];

        $rules = (new StoreInvoiceRequest())->rules();

        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
    }
}

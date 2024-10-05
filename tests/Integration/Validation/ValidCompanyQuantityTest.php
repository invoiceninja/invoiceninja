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

namespace Tests\Integration\Validation;

use Tests\TestCase;
use App\Models\Company;
use Tests\MockUnitData;
use Illuminate\Support\Facades\Validator;
use App\Http\ValidationRules\Company\ValidCompanyQuantity;

/**
 * 
 */
class ValidCompanyQuantityTest extends TestCase
{
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

    }

    /**  */
    public function testCompanyQuantityValidation()
    {
        auth()->login($this->user, true);

        $data =[];
        $rules = ['name' => [new ValidCompanyQuantity()]];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());
    }


    /**  */
    public function testCompanyQuantityValidationFails()
    {
        
        auth()->login($this->user, true);
        auth()->user()->setCompany($this->company);

        $data =['name' => 'bob'];
        $rules = ['name' => [new ValidCompanyQuantity()]];

        Company::factory()->count(10)->create([
            'account_id' => auth()->user()->account->id,
        ]);
        
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());
    }

}

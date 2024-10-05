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
use App\Http\ValidationRules\Company\ValidSubdomain;

/**
 * 
 */
class ValidSubdomainTest extends TestCase
{
    use MockUnitData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**  */
    public function testCheckValidSubdomainName()
    {
        
        $data = ['subdomain' => 'invoiceyninjay'];
        $rules = ['subdomain' => ['nullable', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',new ValidSubdomain()]];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());

    }

    public function testCheckEmptyValidSubdomainName()
    {
        
        $data = ['subdomain' => ''];
        $rules = ['subdomain' => ['nullable', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',new ValidSubdomain()]];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());

    }

    public function testCheckEmpty2ValidSubdomainName()
    {
        
        $data = ['subdomain' => ' '];
        $rules = ['subdomain' => ['nullable', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',new ValidSubdomain()]];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->passes());

    }

    /**  */
    public function testCheckInValidSubdomainName()
    {

        $data = ['subdomain' => 'domain.names'];
        $rules = ['subdomain' => ['nullable', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/',new ValidSubdomain()]];

        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->passes());

      
    }

}

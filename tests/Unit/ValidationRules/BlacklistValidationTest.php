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

use App\Http\ValidationRules\Account\BlackListRule;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\ValidationRules\Account\BlackListRule
 */
class BlacklistValidationTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testValidEmailRule()
    {
        $rules = [
            'email' => [new BlackListRule],
        ];

        $data = [
            'email' => 'jimmy@gmail.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }

    public function testInValidEmailRule()
    {
        $rules = [
            'email' => [new BlackListRule],
        ];

        $data = [
            'email' => 'jimmy@candassociates.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }
}

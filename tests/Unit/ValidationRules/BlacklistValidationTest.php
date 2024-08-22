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

use Tests\TestCase;
use App\Http\ValidationRules\Account\BlackListRule;
use App\Http\ValidationRules\Account\EmailBlackListRule;

/**
 * @test
 * @covers App\Http\ValidationRules\Account\BlackListRule
 */
class BlacklistValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testValidEmailRule3()
    {
        $rules = [
            'email' => [new EmailBlackListRule()],
        ];

        $data = [
            'email' => 'contact@invoiceninja.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }


    public function testValidEmailRule2()
    {
        $rules = [
            'email' => [new EmailBlackListRule()],
        ];

        $data = [
            'email' => 'noddy@invoiceninja.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testValidEmailRule()
    {
        $rules = [
            'email' => [new BlackListRule()],
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
            'email' => [new BlackListRule()],
        ];

        $data = [
            'email' => 'jimmy@candassociates.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testInValidEmailRule2()
    {
        $rules = [
            'email' => [new BlackListRule()],
        ];

        $data = [
            'email' => 'jimmy@zzz.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testInValidEmailRule3()
    {
        $rules = [
            'email' => [new BlackListRule()],
        ];

        $data = [
            'email' => 'jimmy@gmail.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }
}

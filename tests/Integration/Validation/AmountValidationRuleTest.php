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

use App\Http\ValidationRules\ValidAmount;
use Tests\TestCase;

/**
 * @test
 */
class AmountValidationRuleTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();
    }

    public function testSimpleAmountValid()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => 1,
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }

    public function testInvalidAmountValid()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => 'aa',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testIllegalChars()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => '5+5',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testIllegalCharsNaked()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => 5 + 5, //resolves as 10 - but in practice, i believe this amount is wrapped in quotes so interpreted as a string
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }

    public function testinValidScenario1()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => '-10x',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testValidScenario2()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => -10,
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }

    public function testValidScenario3()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => '-10',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }

    public function testInValidScenario4()
    {
        $rules = [
            'amount' => [new ValidAmount()],
        ];

        $data = [
            'amount' => '-0 1',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }
}

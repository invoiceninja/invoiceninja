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

use App\Http\ValidationRules\Account\EmailBlackListRule;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\ValidationRules\Account\EmailBlackListRule
 */
class EmailBlacklistValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testValidEmailRule()
    {
        $email_rule = new EmailBlackListRule();
        $email_rule->blacklist = ['gimmy@gmail.com'];

        $rules = [
            'email' => [$email_rule],
        ];

        $data = [
            'email' => 'gimmy@gmail.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertFalse($v->passes());
    }

    public function testInValidEmailRule()
    {
        $rules = [
            'email' => [new EmailBlackListRule()],
        ];

        $data = [
            'email' => 'jimmy@candassociates.com',
        ];

        $v = $this->app['validator']->make($data, $rules);
        $this->assertTrue($v->passes());
    }
}

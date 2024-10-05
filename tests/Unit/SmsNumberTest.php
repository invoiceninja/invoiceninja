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

use App\DataProviders\SMSNumbers;
use Tests\TestCase;

/**
 * 
 */
class SmsNumberTest extends TestCase
{
    public function testArrayHit()
    {
        $this->assertTrue(SMSNumbers::hasNumber("+461614222"));
    }

    public function testArrayMiss()
    {
        $this->assertFalse(SMSNumbers::hasNumber("+5485454"));
    }

    public function testSmsArrayType()
    {
        $this->assertIsArray(SMSNumbers::getNumbers());
    }
}

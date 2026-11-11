<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Export\Decorators\Decorator;
use App\Models\Payment;
use Tests\TestCase;

/**
 */
class DecoratorTest extends TestCase
{
    public function testBareEntityKeyReturnsNull()
    {
        $decorator = new Decorator();

        $this->assertNull($decorator->transform('client', new Payment()));
    }

    public function testBareUnknownKeyReturnsNull()
    {
        $decorator = new Decorator();

        $this->assertNull($decorator->transform('not_an_entity', new Payment()));
    }

    public function testUnknownEntityKeyWithColumnReturnsNull()
    {
        $decorator = new Decorator();

        $this->assertNull($decorator->transform('not_an_entity.name', new Payment()));
    }

    public function testEmptyKeyReturnsNull()
    {
        $decorator = new Decorator();

        $this->assertNull($decorator->transform('', new Payment()));
    }
}

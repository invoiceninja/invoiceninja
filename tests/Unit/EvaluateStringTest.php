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

use App\Models\Client;
use Tests\TestCase;

/**
 * 
 */
class EvaluateStringTest extends TestCase
{
    public function testNumericCleanup()
    {
        $string = '13/favicon.ico';

        $number = preg_replace('~\D~', '', $string);

        $this->assertEquals(13, $number);
    }

    public function testClassNameResolution()
    {
        $this->assertEquals(class_basename(Client::class), 'Client');
    }

}

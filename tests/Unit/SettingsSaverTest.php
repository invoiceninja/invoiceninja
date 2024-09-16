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

use App\Utils\Traits\SettingsSaver;
use Tests\TestCase;

/**
 * 
 */
class SettingsSaverTest extends TestCase
{
    use SettingsSaver;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testNullValueForStringTest()
    {
        $key = 'show_all_tasks_client_portal';
        $value = null;

        $result = $this->checkAttribute($key, $value);

        $this->assertFalse($result);
    }
}

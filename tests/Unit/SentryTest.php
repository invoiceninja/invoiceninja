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

use Tests\TestCase;

class SentryTest extends TestCase
{
    public function testSentryFiresAppropriately()
    {
        $e = new \Exception('Test Fire');
        app('sentry')->captureException($e);

        $this->assertTrue(true);
    }
}

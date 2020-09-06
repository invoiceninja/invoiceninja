<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
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

<?php

namespace Tests\Unit;

use App\DataMapper\DefaultSettings;
use App\Models\Client;
use Tests\TestCase;

/**
 * @test
 */
class EvaluateStringTest extends TestCase
{
    public function testClassNameResolution()
    {
        $this->assertEquals(class_basename(Client::class), 'Client');
    }
}

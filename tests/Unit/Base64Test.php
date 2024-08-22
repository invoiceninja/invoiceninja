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

use App\Utils\Ninja;
use Tests\TestCase;

/**
 * @test
 */
class Base64Test extends TestCase
{
    /**
     * Important consideration with Base64
     * encoding checks.
     *
     * No method can guarantee against false positives.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testBadBase64String()
    {
        $this->assertFalse(Ninja::isBase64Encoded('x'));
    }

    public function testCorrectBase64Encoding()
    {
        $this->assertTrue(Ninja::isBase64Encoded('MTIzNDU2'));
    }

    public function testBadBase64StringScenaro1()
    {
        $this->assertFalse(Ninja::isBase64Encoded('Matthies'));
    }

    public function testBadBase64StringScenaro2()
    {
        $this->assertFalse(Ninja::isBase64Encoded('Barthels'));
    }

    public function testBadBase64StringScenaro3()
    {
        $this->assertFalse(Ninja::isBase64Encoded('aaa'));
    }
}

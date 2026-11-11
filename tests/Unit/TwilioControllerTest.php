<?php

namespace Tests\Unit;

use App\Http\Controllers\TwilioController;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class TwilioControllerTest extends TestCase
{
    private TwilioController $controller;

    private ReflectionMethod $check_phone_validity;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('invalid_phone_codes');

        $this->controller = new TwilioController();
        $this->check_phone_validity = new ReflectionMethod($this->controller, 'checkPhoneValidity');
        $this->check_phone_validity->setAccessible(true);
    }

    protected function tearDown(): void
    {
        Cache::forget('invalid_phone_codes');

        parent::tearDown();
    }

    public function testAllStaticInvalidPhoneCodesAreChecked(): void
    {
        foreach ($this->staticInvalidPhoneCodes() as $code) {
            $this->assertFalse($this->phoneIsValid("{$code}00000000"));
        }

        $this->assertTrue($this->phoneIsValid('+15551234567'));
    }

    public function testAdminInvalidPhoneCodesAreMergedWithStaticCodes(): void
    {
        Cache::put('invalid_phone_codes', [' +61 ', '+4478', '', null]);

        $this->assertFalse($this->phoneIsValid('+61412345678'));
        $this->assertFalse($this->phoneIsValid('+93000000000'));
        $this->assertTrue($this->phoneIsValid('+64123456789'));
    }

    public function testInvalidCachedValueDoesNotReplaceStaticCodes(): void
    {
        Cache::put('invalid_phone_codes', 'not-an-array');

        $static_code = $this->staticInvalidPhoneCodes()[0];

        $this->assertFalse($this->phoneIsValid("{$static_code}00000000"));
        $this->assertTrue($this->phoneIsValid('+15551234567'));
    }

    private function phoneIsValid(string $phone): bool
    {
        return $this->check_phone_validity->invoke($this->controller, $phone);
    }

    /**
     * @return array<int, string>
     */
    private function staticInvalidPhoneCodes(): array
    {
        $property = new ReflectionProperty($this->controller, 'invalid_codes');
        $property->setAccessible(true);

        return $property->getValue($this->controller);
    }
}

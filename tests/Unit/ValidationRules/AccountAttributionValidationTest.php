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

namespace Tests\Unit\ValidationRules;

use App\Http\Requests\Account\CreateAccountRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;
use Tests\TestCase;

class AccountAttributionValidationTest extends TestCase
{
    private function validate(array $data): LaravelValidator
    {
        return Validator::make($data, Arr::only(
            (new CreateAccountRequest())->rules(),
            ['attribution', 'attribution.*']
        ));
    }

    public function testUtmAttributionPasses(): void
    {
        $this->assertTrue($this->validate([
            'attribution' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'january_launch',
            ],
        ])->passes());
    }

    public function testAttributionIsOptional(): void
    {
        $this->assertTrue($this->validate([])->passes());
        $this->assertTrue($this->validate(['attribution' => null])->passes());
    }

    public function testNonUtmKeyIsRejected(): void
    {
        $validator = $this->validate([
            'attribution' => [
                'utm_source' => 'facebook',
                'fbclid'     => 'abc123',
            ],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('attribution', $validator->errors()->toArray());
    }

    public function testMoreThanTwentyKeysAreRejected(): void
    {
        $keys = [];

        for ($i = 0; $i <= 20; $i++) {
            $keys['utm_key'.$i] = 'value';
        }

        $this->assertFalse($this->validate(['attribution' => $keys])->passes());
        $this->assertTrue($this->validate(['attribution' => array_slice($keys, 0, 20, true)])->passes());
    }

    public function testValueLongerThanColumnIsRejected(): void
    {
        $this->assertTrue($this->validate([
            'attribution' => ['utm_source' => str_repeat('a', 191)],
        ])->passes());

        $this->assertFalse($this->validate([
            'attribution' => ['utm_source' => str_repeat('a', 192)],
        ])->passes());
    }

    public function testKeyLongerThanSixtyThreeCharactersIsRejected(): void
    {
        $this->assertTrue($this->validate([
            'attribution' => ['utm_'.str_repeat('a', 59) => 'value'],
        ])->passes());

        $this->assertFalse($this->validate([
            'attribution' => ['utm_'.str_repeat('a', 60) => 'value'],
        ])->passes());
    }

    public function testNonStringValueIsRejected(): void
    {
        $this->assertFalse($this->validate([
            'attribution' => ['utm_source' => ['nested']],
        ])->passes());
    }

    public function testLegacyUtmColumnsAreCappedAtTheColumnLength(): void
    {
        $rules = (new CreateAccountRequest())->rules()['utm_source'];

        $this->assertTrue(Validator::make(
            ['utm_source' => str_repeat('a', 191)],
            ['utm_source' => $rules]
        )->passes());

        $this->assertFalse(Validator::make(
            ['utm_source' => str_repeat('a', 192)],
            ['utm_source' => $rules]
        )->passes());
    }
}

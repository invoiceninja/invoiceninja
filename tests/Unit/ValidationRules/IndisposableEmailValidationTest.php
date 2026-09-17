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
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelDisposableEmail\Facades\DisposableDomains;
use Tests\TestCase;

class IndisposableEmailValidationTest extends TestCase
{
    public function testCreateAccountRequestUsesIndisposableMxRule(): void
    {
        $rules = (new CreateAccountRequest())->rules()['email'];

        $this->assertContains('indisposable:mx', $rules);
    }

    public function testDisposableDomainEmailFailsIndisposableRule(): void
    {
        $validator = Validator::make(
            ['email' => 'user@mailinator.com'],
            ['email' => ['email', 'indisposable']]
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function testLegitimateEmailPassesIndisposableRule(): void
    {
        $validator = Validator::make(
            ['email' => 'contact@gmail.com'],
            ['email' => ['email', 'indisposable']]
        );

        $this->assertTrue($validator->passes());
    }

    public function testMxInspectionRejectsDomainPointingAtDisposableMailServer(): void
    {
        DisposableDomains::setMxResolver(function (string $domain): array {
            if ($domain === 'fresh-front-domain.example') {
                return [
                    [
                        'type' => 'MX',
                        'target' => 'mail.mailinator.com',
                    ],
                ];
            }

            return [];
        });

        $withoutMx = Validator::make(
            ['email' => 'user@fresh-front-domain.example'],
            ['email' => ['email', 'indisposable']]
        );

        $withMx = Validator::make(
            ['email' => 'user@fresh-front-domain.example'],
            ['email' => ['email', 'indisposable:mx']]
        );

        $this->assertTrue($withoutMx->passes());
        $this->assertFalse($withMx->passes());
        $this->assertArrayHasKey('email', $withMx->errors()->toArray());
    }

    public function testCreateAccountEmailRulesRejectDisposableAddress(): void
    {
        $request = new CreateAccountRequest();

        $validator = Validator::make(
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'password' => 'secret123',
                'email' => 'user@mailinator.com',
                'privacy_policy' => true,
                'terms_of_service' => true,
            ],
            $request->rules()
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
}

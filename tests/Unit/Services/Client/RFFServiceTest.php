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

namespace Tests\Unit\Services\Client;

use App\Services\Client\RFFService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\MockAccountData;
use Tests\TestCase;

class RFFServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testRulesForFieldsReadsValidationKeyAndSplitsCommas(): void
    {
        $rules = RFFService::rulesForFields([
            ['name' => 'contact_email', 'validation' => 'required,email:rfc'],
        ]);

        $this->assertSame(
            ['required', 'email:rfc', 'not_regex:/@example\.com$/i'],
            $rules['contact_email']
        );
    }

    public function testRulesForFieldsReadsPipeDelimitedAndValidationRulesKey(): void
    {
        $rules = RFFService::rulesForFields([
            ['name' => 'contact_email', 'validation_rules' => 'required|email:rfc'],
        ]);

        $this->assertSame(
            ['required', 'email:rfc', 'not_regex:/@example\.com$/i'],
            $rules['contact_email']
        );
    }

    public function testRulesForFieldsPrefersValidationOverValidationRules(): void
    {
        $rules = RFFService::rulesForFields([
            [
                'name' => 'contact_email',
                'validation' => 'required,email:rfc',
                'validation_rules' => 'required',
            ],
        ]);

        $this->assertSame(
            ['required', 'email:rfc', 'not_regex:/@example\.com$/i'],
            $rules['contact_email']
        );
    }

    public function testRulesForFieldsSkipsFilledFields(): void
    {
        $rules = RFFService::rulesForFields([
            ['name' => 'contact_email', 'validation' => 'required,email:rfc', 'filled' => true],
        ]);

        $this->assertArrayNotHasKey('contact_email', $rules);
    }

    public function testParsedEmailRulesRejectInvalidAndAcceptValid(): void
    {
        $fields = [
            ['name' => 'contact_email', 'validation' => 'required,email:rfc'],
        ];
        $rules = RFFService::rulesForFields($fields);

        $this->assertTrue(Validator::make(['contact_email' => 'bob@'], $rules)->fails());
        $this->assertTrue(Validator::make(['contact_email' => 'YiXEiLzAqcAhfiq@example.com'], $rules)->fails());
        $this->assertTrue(Validator::make(['contact_email' => 'user@example.com'], $rules)->fails());
        $this->assertTrue(Validator::make(['contact_email' => 'user@sub.example.com'], $rules)->passes());
        $this->assertTrue(Validator::make(['contact_email' => 'user@example.org'], $rules)->passes());
    }

    public function testPassesExistingValuesWithValidContactData(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->first_name = 'Jane';
        $contact->last_name = 'Doe';
        $contact->email = 'jane.doe@example.org';
        $contact->save();

        $fields = [
            ['name' => 'contact_first_name', 'validation' => 'required'],
            ['name' => 'contact_last_name', 'validation' => 'required'],
            ['name' => 'contact_email', 'validation' => 'required,email:rfc'],
        ];

        $this->assertTrue(RFFService::passesExistingValues($contact->fresh(), $fields));
    }

    public function testPassesExistingValuesRejectsInvalidEmail(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->first_name = 'Jane';
        $contact->last_name = 'Doe';
        $contact->email = 'bob@';
        $contact->save();

        $fields = [
            ['name' => 'contact_first_name', 'validation' => 'required'],
            ['name' => 'contact_last_name', 'validation' => 'required'],
            ['name' => 'contact_email', 'validation' => 'required,email:rfc'],
        ];

        $this->assertFalse(RFFService::passesExistingValues($contact->fresh(), $fields));
    }

    public function testPassesExistingValuesRejectsDemoEmail(): void
    {
        $contact = $this->client->contacts()->where('is_primary', true)->first();
        $contact->first_name = 'Jane';
        $contact->last_name = 'Doe';
        $contact->email = 'user@example.com';
        $contact->save();

        $fields = [
            ['name' => 'contact_email', 'validation' => 'required,email:rfc'],
        ];

        $this->assertFalse(RFFService::passesExistingValues($contact->fresh(), $fields));
    }
}

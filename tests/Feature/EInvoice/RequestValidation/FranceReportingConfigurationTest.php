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

namespace Tests\Feature\EInvoice\RequestValidation;

use App\Models\Country;
use App\Models\CompanyUser;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Services\EDocument\Standards\France\ReportingProfile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class FranceReportingConfigurationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('FranceReportingConfigurationTest is not implemented');
        config(['ninja.environment' => 'hosted']);
        $this->makeTestData();
        $this->enableFranceReporting();
    }

    #[DataProvider('recognizedTrueValues')]
    public function test_hosted_peppol_company_coerces_recognized_true_values(mixed $value): void
    {
        $this->disableFranceReporting();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('recognizedTrueValues')]
    public function test_hosted_enabled_company_accepts_every_recognized_true_value(mixed $value): void
    {
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('recognizedFalseValues')]
    public function test_hosted_disabled_company_coerces_recognized_false_values(mixed $value): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertFalse($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('recognizedFalseValues')]
    public function test_hosted_enabled_company_rejects_every_recognized_false_value(mixed $value): void
    {
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('invalidBooleanValues')]
    public function test_company_update_rejects_values_that_cannot_be_coerced_to_boolean(mixed $value): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertFalse($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('invalidBooleanValues')]
    public function test_enabled_company_rejects_values_that_cannot_be_coerced_to_boolean(mixed $value): void
    {
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_does_not_change_enabled_reporting_when_the_property_is_missing(): void
    {
        $settings = (array) $this->company->fresh()->settings;
        unset($settings['france_reporting_enabled']);

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_does_not_enable_reporting_when_the_property_is_missing(): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        unset($settings['france_reporting_enabled']);

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertFalse($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('recognizedTrueValues')]
    public function test_self_hosted_company_skips_the_hosted_business_rule(mixed $value): void
    {
        config(['ninja.environment' => 'selfhost']);
        $this->disableFranceReporting();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_false_string_disables_schedule_validation_when_reporting_is_already_disabled(): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = 'false';
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $company = $this->company->fresh();
        $this->assertFalse($company->getSetting('france_reporting_enabled'));
        $this->assertSame('weekly', $company->getSetting('france_reporting_schedule'));
    }

    public function test_true_string_enables_schedule_validation(): void
    {
        $this->disableFranceReporting();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = 'true';
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_schedule');

        $this->assertFalse($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_rejects_disabling_reporting_after_history_exists(): void
    {
        $this->reportingEvent(
            FranceReportingEventType::DocumentLifecycle,
            null,
        );
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = false;

        $response = $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');
        $this->assertTrue((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_allows_valid_schedule_changes(): void
    {
        $before = TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count();
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_schedule'] = ReportingProfile::Monthly->value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertSame(
            ReportingProfile::Monthly->value,
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
        $this->assertSame($before + 1, TransactionEvent::query()
            ->where('company_id', $this->company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->count());
    }

    public function test_company_update_rejects_disabling_reporting_before_history_exists(): void
    {
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = false;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertTrue((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_rejects_disabling_reporting_with_only_transient_events(): void
    {
        $this->reportingEvent(
            FranceReportingEventType::SubmissionCallback,
            FranceReportingStatus::Pending,
        );
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = false;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertTrue((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('recognizedFalseValues')]
    public function test_self_hosted_company_can_disable_reporting(mixed $value): void
    {
        config(['ninja.environment' => 'selfhost']);
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    #[DataProvider('invalidBooleanValues')]
    public function test_self_hosted_company_still_rejects_invalid_boolean_values(mixed $value): void
    {
        config(['ninja.environment' => 'selfhost']);
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_enabled'] = $value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertTrue($this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_reporting_checks_return_early_when_reporting_is_disabled(): void
    {
        $this->disableFranceReporting();
        $this->reportingEvent(
            FranceReportingEventType::ReportSubmission,
            FranceReportingStatus::Accepted,
        );
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_schedule'] = ReportingProfile::Monthly->value;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertSame(
            ReportingProfile::Monthly->value,
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
    }

    public function test_company_update_rejects_an_unknown_reporting_schedule(): void
    {
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_schedule');
    }

    public function test_company_update_does_not_validate_the_schedule_when_reporting_is_disabled(): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertSame(
            'weekly',
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
    }

    public function test_self_hosted_company_does_not_validate_the_reporting_schedule(): void
    {
        config(['ninja.environment' => 'selfhost']);
        $settings = (array) $this->company->fresh()->settings;
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $this->assertSame(
            'weekly',
            $this->company->fresh()->getSetting('france_reporting_schedule'),
        );
    }

    public function test_company_update_validates_the_schedule_when_reporting_is_enabled_in_the_same_request(): void
    {
        $this->disableFranceReporting();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = true;
        $settings['france_reporting_schedule'] = 'weekly';

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_schedule');

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_peppol_setup_uses_the_authenticated_company_key(): void
    {
        config([
            'ninja.app_env' => 'local',
            'ninja.environment' => 'selfhost',
        ]);
        $this->company->legal_entity_id = null;
        $this->company->saveQuietly();
        Http::fake([
            '*/api/einvoice/peppol/setup' => Http::response(['legal_entity_id' => 54321]),
        ]);
        $data = $this->peppolSetupData();
        $data['tenant_id'] = 'caller-controlled-company-key';

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/einvoice/peppol/setup', $data)
            ->assertNoContent();

        Http::assertSent(fn($request): bool => $request['tenant_id'] === $this->company->company_key);
        $this->assertSame(54321, (int) $this->company->fresh()->legal_entity_id);
    }

    public function test_non_admin_cannot_disconnect_peppol(): void
    {
        config(['ninja.app_env' => 'production']);
        Http::fake();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        CompanyUser::query()
            ->where('user_id', $this->user->id)
            ->where('company_id', $this->company->id)
            ->update(['is_admin' => false]);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/einvoice/peppol/disconnect', [
                'company_key' => $this->company->company_key,
            ])
            ->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertSame(12345, (int) $this->company->fresh()->legal_entity_id);
    }

    public function test_admin_can_disconnect_peppol(): void
    {
        config([
            'ninja.app_env' => 'local',
            'ninja.environment' => 'selfhost',
        ]);
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        Http::fake([
            '*/api/einvoice/peppol/disconnect' => Http::response([]),
        ]);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/einvoice/peppol/disconnect', [
                'company_key' => $this->company->company_key,
            ])
            ->assertOk();

        $company = $this->company->fresh();
        $this->assertNull($company->legal_entity_id);
        $this->assertSame('EN16931', $company->getSetting('e_invoice_type'));
        $this->assertFalse((bool) $company->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_rejects_enabling_reporting_without_peppol(): void
    {
        $this->disableFranceReporting();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'EN16931';
        $settings['france_reporting_enabled'] = true;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_company_update_allows_enabling_reporting_with_peppol(): void
    {
        $this->disableFranceReporting();
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = true;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertSuccessful();

        $company = $this->company->fresh();
        $this->assertSame('PEPPOL', $company->getSetting('e_invoice_type'));
        $this->assertTrue((bool) $company->getSetting('france_reporting_enabled'));
        $invalidation = TransactionEvent::query()
            ->where('company_id', $company->id)
            ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue((bool) data_get(
            $invalidation->payment_request,
            'initialize_current_periods',
        ));
    }

    public function test_company_update_rejects_enabling_reporting_without_a_peppol_legal_entity(): void
    {
        $this->disableFranceReporting();
        $this->company->legal_entity_id = null;
        $this->company->saveQuietly();
        $settings = (array) $this->company->fresh()->settings;
        $settings['e_invoice_type'] = 'PEPPOL';
        $settings['france_reporting_enabled'] = true;

        $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/' . $this->company->hashed_id, [
                'settings' => $settings,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings.france_reporting_enabled');

        $this->assertFalse((bool) $this->company->fresh()->getSetting('france_reporting_enabled'));
    }

    public function test_peppol_reporting_check_returns_early_when_reporting_is_disabled(): void
    {
        config(['ninja.app_env' => 'local']);
        $this->company->legal_entity_id = 12345;
        $this->company->saveQuietly();
        $this->disableFranceReporting();
        $this->reportingEvent(
            FranceReportingEventType::ReportSubmission,
            FranceReportingStatus::Accepted,
        );
        Http::fake(['*' => Http::response([])]);

        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/einvoice/peppol/disconnect', [
                'company_key' => $this->company->company_key,
            ])
            ->assertOk();

        $this->assertNull($this->company->fresh()->legal_entity_id);
    }

    private function enableFranceReporting(): void
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = ReportingProfile::TenDay->value;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->company->refresh();
    }

    private function disableFranceReporting(): void
    {
        $settings = clone $this->company->settings;
        $settings->france_reporting_enabled = false;
        $this->company->settings = $settings;
        $this->company->saveQuietly();
        $this->company->refresh();
    }

    private function reportingEvent(
        FranceReportingEventType $eventType,
        ?FranceReportingStatus $status,
    ): TransactionEvent {
        return TransactionEvent::create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $this->invoice->id,
            'payment_id' => 0,
            'credit_id' => 0,
            'event_id' => $eventType->value,
            'timestamp' => now()->timestamp,
            'period' => now()->toDateString(),
            'payment_status' => $status?->value,
            'reporting_data' => null,
            'payment_request' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function peppolSetupData(): array
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();

        return [
            'party_name' => 'French Test Company',
            'line1' => '1 Rue de Test',
            'line2' => null,
            'city' => 'Paris',
            'country' => $france->id,
            'zip' => '75001',
            'county' => 'Paris',
            'acts_as_sender' => true,
            'acts_as_receiver' => true,
            'tenant_id' => $this->company->company_key,
            'classification' => 'business',
            'vat_number' => 'FR44732829320',
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function recognizedTrueValues(): array
    {
        return [
            'boolean true' => [true],
            'integer one' => [1],
            'string one' => ['1'],
            'lowercase true' => ['true'],
            'uppercase true' => ['TRUE'],
            'trimmed true' => [' true '],
            'on' => ['on'],
            'yes' => ['yes'],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function recognizedFalseValues(): array
    {
        return [
            'boolean false' => [false],
            'integer zero' => [0],
            'string zero' => ['0'],
            'lowercase false' => ['false'],
            'uppercase false' => ['FALSE'],
            'trimmed false' => [' false '],
            'off' => ['off'],
            'no' => ['no'],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function invalidBooleanValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
            'integer two' => [2],
            'negative integer' => [-1],
            'numeric string two' => ['2'],
            'arbitrary string' => ['enabled'],
            'empty array' => [[]],
            'associative array' => [['value' => true]],
        ];
    }

    /** @return array<string, string> */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }
}

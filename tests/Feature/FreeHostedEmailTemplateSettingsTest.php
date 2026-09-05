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

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Expectation: hosted free accounts cannot persist settings outside
 * CompanySettings::$free_plan_casts on company or client settings.
 *
 * These tests currently fail: UpdateCompanyRequest / UpdateClientRequest
 * unset filtered keys with object syntax on JSON arrays, and
 * StoreClientRequest does not apply the free-plan filter at all.
 */
class FreeHostedEmailTemplateSettingsTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    private const INJECTED_MARKER = 'FREE_HOSTED_UNFILTERED_SETTING';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Model::reguard();
    }

    public function testRestrictedSettingsAreNotInTheFreePlanAllowlist(): void
    {
        foreach ($this->restrictedSettingKeys() as $key) {
            $this->assertArrayNotHasKey(
                $key,
                CompanySettings::$free_plan_casts,
                "{$key} is on the free-plan allowlist; this test would not prove a filter miss"
            );
        }
    }

    public function testFreeHostedCompanyUpdateFiltersRestrictedSettings(): void
    {
        $this->becomeFreeHostedClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/'.$this->company->hashed_id, [
                'settings' => $this->restrictedSettingsPayload((array) $this->company->settings),
            ]);

        $response->assertStatus(200);

        $this->company->refresh();
        $this->assertRestrictedSettingsWereFiltered($this->company->settings);
        $this->assertRestrictedSettingsWereFiltered($response->json('data.settings'));
    }

    public function testFreeHostedCompanyUpdateDoesNotOverwriteRestrictedSettings(): void
    {
        $this->seedRestrictedSettings($this->company->settings, 'EXISTING_COMPANY_SETTING');
        $this->company->save();

        $this->becomeFreeHostedClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/companies/'.$this->company->hashed_id, [
                'settings' => $this->restrictedSettingsPayload((array) $this->company->fresh()->settings),
            ]);

        $response->assertStatus(200);

        $this->company->refresh();
        $this->assertRestrictedSettingsWereFiltered($this->company->settings);
        $this->assertRestrictedSettingsWereFiltered($response->json('data.settings'));
    }

    public function testFreeHostedClientCreateFiltersRestrictedSettings(): void
    {
        $this->becomeFreeHostedClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/clients/', [
                'name' => 'Free Hosted Filter Probe',
                'settings' => $this->restrictedSettingsPayload([
                    'currency_id' => '1',
                ]),
            ]);

        $response->assertStatus(200);

        $client = Client::find($this->decodePrimaryKey($response->json('data.id')));

        $this->assertNotNull($client);
        $this->assertRestrictedSettingsWereFiltered($client->settings);
        $this->assertRestrictedSettingsWereFiltered($response->json('data.settings'));
    }

    public function testFreeHostedClientUpdateFiltersRestrictedSettings(): void
    {
        $this->becomeFreeHostedClient();

        $response = $this->withHeaders($this->apiHeaders())
            ->putJson('/api/v1/clients/'.$this->client->hashed_id, [
                'settings' => $this->restrictedSettingsPayload([
                    'currency_id' => '1',
                ]),
            ]);

        $response->assertStatus(200);

        $this->client->refresh();
        $this->assertRestrictedSettingsWereFiltered($this->client->settings);
        $this->assertRestrictedSettingsWereFiltered($response->json('data.settings'));
    }

    private function becomeFreeHostedClient(): void
    {
        config([
            'ninja.environment' => 'hosted',
            'ninja.production' => true,
        ]);

        $this->account->plan = Account::PLAN_FREE;
        $this->account->plan_expires = null;
        $this->account->trial_plan = null;
        $this->account->trial_started = null;
        $this->account->save();
        $this->account->refresh();

        $this->company->unsetRelation('account');
        $this->company->refresh();
        $this->user->unsetRelation('account');

        $this->assertTrue($this->account->isFreeHostedClient());
        $this->assertTrue($this->company->account->isFreeHostedClient());
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /**
     * @param  array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function restrictedSettingsPayload(array $settings): array
    {
        foreach ($this->restrictedSettingKeys() as $key) {
            $settings[$key] = $this->injectedValue($key);
        }

        return $settings;
    }

    private function seedRestrictedSettings(object $settings, string $prefix): void
    {
        foreach ($this->restrictedSettingKeys() as $key) {
            $settings->{$key} = $this->isBooleanSetting($key) ? false : $prefix.' '.$key;
        }
    }

    private function injectedValue(string $key): mixed
    {
        if ($this->isBooleanSetting($key)) {
            return true;
        }

        return self::INJECTED_MARKER.' '.$key;
    }

    private function assertRestrictedSettingsWereFiltered(object|array $settings): void
    {
        $settings = (array) json_decode(json_encode($settings), true);

        foreach ($this->restrictedSettingKeys() as $key) {
            $value = $settings[$key] ?? null;

            if ($this->isBooleanSetting($key)) {
                $this->assertNotTrue(
                    (bool) $value,
                    "Free hosted user persisted restricted setting {$key}"
                );

                continue;
            }

            $this->assertNotSame(
                $this->injectedValue($key),
                $value,
                "Free hosted user persisted restricted setting {$key}"
            );
            $this->assertStringNotContainsString(
                self::INJECTED_MARKER,
                (string) $value,
                "Free hosted user persisted restricted setting {$key}"
            );
        }
    }

    /**
     * Settings that are intentionally absent from $free_plan_casts.
     *
     * @return list<string>
     */
    private function restrictedSettingKeys(): array
    {
        $email_template_keys = array_values(array_filter(
            array_keys(CompanySettings::$casts),
            fn (string $key): bool => str_starts_with($key, 'email_template_')
                || str_starts_with($key, 'email_subject_')
                || $key === 'email_style_custom'
        ));

        return array_values(array_unique(array_merge($email_template_keys, [
            'auto_archive_invoice',
            'invoice_terms',
        ])));
    }

    private function isBooleanSetting(string $key): bool
    {
        return (CompanySettings::$casts[$key] ?? null) === 'bool';
    }
}

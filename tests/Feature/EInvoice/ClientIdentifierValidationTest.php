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

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use Tests\MockAccountData;
use App\Models\ClientContact;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;

class ClientIdentifierValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        $this->testCompany = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
    }

    private function makeClient(array $overrides = []): Client
    {
        $client = Client::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->testCompany->id,
            'country_id' => 56, // BE
            'classification' => 'business',
            'address1' => '1 Rue de la Loi',
            'city' => 'Brussels',
            'postal_code' => '1000',
            'vat_number' => 'BE0202239951',
        ], $overrides));

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->testCompany->id,
            'is_primary' => 1,
            'email' => 'test@example.com',
        ]);

        return $client->fresh();
    }

    private function clientErrors(Client $client): array
    {
        return (new EntityLevel())->checkClient($client)['client'] ?? [];
    }

    private function hasErrorForField(array $errors, string $field): bool
    {
        foreach ($errors as $e) {
            if (($e['field'] ?? null) === $field) {
                return true;
            }
        }

        return false;
    }

    private function firstErrorLabel(array $errors, string $field): ?string
    {
        foreach ($errors as $e) {
            if (($e['field'] ?? null) === $field) {
                return $e['label'] ?? null;
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────
    // Happy path — BE business with a valid vat_number
    // passes because BE:EN and BE:VAT are both derivable.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessWithValidVatNumberPasses(): void
    {
        $client = $this->makeClient();

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], 'BE business with valid vat_number should pass. Errors: ' . json_encode($result['client'] ?? []));
    }

    // ──────────────────────────────────────────────────────
    // Missing input — BE handler reads vat_number only.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessMissingVatNumberIsBlocked(): void
    {
        $client = $this->makeClient(['vat_number' => '']);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'vat_number'));
    }

    // ──────────────────────────────────────────────────────
    // id_number is not consulted for BE — it must not affect
    // the outcome, even when absent.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessIgnoresIdNumber(): void
    {
        $client = $this->makeClient(['id_number' => '']);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], 'BE does not use id_number — absence must not affect validation');
    }

    // ──────────────────────────────────────────────────────
    // Invalid vat_number — format fails regex for both BE:EN
    // and BE:VAT so no candidate is routable.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessInvalidVatFormatIsBlocked(): void
    {
        $client = $this->makeClient(['vat_number' => 'ABCDEF']);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'vat_number'));
    }

    // ──────────────────────────────────────────────────────
    // Bad checkdigit — regex matches, mod-97 fails on both
    // derived candidates.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessInvalidCheckdigitIsBlocked(): void
    {
        // BE0202239951 is valid; mutating the last digit breaks the mod-97.
        $client = $this->makeClient(['vat_number' => 'BE0202239952']);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'vat_number'));
    }

    // ──────────────────────────────────────────────────────
    // Error label mentions both schemes the handler tried —
    // users see that BE:EN or BE:VAT are each acceptable.
    // ──────────────────────────────────────────────────────

    public function testBeErrorLabelListsBothSchemes(): void
    {
        $client = $this->makeClient(['vat_number' => 'ABCDEF']);

        $label = $this->firstErrorLabel($this->clientErrors($client), 'vat_number');

        $this->assertNotNull($label);
        $this->assertStringContainsString('BE:EN', $label);
        $this->assertStringContainsString('BE:VAT', $label);
    }

    // ──────────────────────────────────────────────────────
    // DE business — handler reads vat_number, produces DE:VAT.
    // ──────────────────────────────────────────────────────

    public function testDeBusinessMissingVatNumberIsBlocked(): void
    {
        $client = $this->makeClient([
            'country_id' => 276, // DE
            'vat_number' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'vat_number'));
    }

    // ──────────────────────────────────────────────────────
    // Individual — gated out by checkDeliveryNetwork (BE
    // individual is not routable) so identifier block is
    // short-circuited by the count($errors) === 0 check.
    // ──────────────────────────────────────────────────────

    public function testBeIndividualSkipsIdentifierChecks(): void
    {
        $client = $this->makeClient([
            'classification' => 'individual',
            'vat_number' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertFalse($this->hasErrorForField($errors, 'vat_number'), 'Identifier errors should not pile on while classification is unroutable');
    }

    // ──────────────────────────────────────────────────────
    // US is not in peppol_network — identifier block is
    // skipped entirely.
    // ──────────────────────────────────────────────────────

    public function testUsBusinessSkipsIdentifierChecks(): void
    {
        $client = $this->makeClient([
            'country_id' => 840, // US
            'vat_number' => '',
        ]);

        $errors = $this->clientErrors($client);

        // US is outside peppol_network, so checkDeliveryNetwork will itself
        // add a classification error and the identifier block never runs.
        $this->assertFalse($this->hasErrorForField($errors, 'vat_number'), 'US is outside peppol_network — identifier validation does not fire');
    }

    // ──────────────────────────────────────────────────────
    // Earlier field errors short-circuit identifier validation
    // ──────────────────────────────────────────────────────

    public function testIdentifierChecksSkippedWhenEarlierErrorsExist(): void
    {
        $client = $this->makeClient([
            'address1' => '',
            'vat_number' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'address1'));
        $this->assertFalse($this->hasErrorForField($errors, 'vat_number'), 'Identifier errors should not pile on while address is unresolved');
    }

    // ──────────────────────────────────────────────────────
    // All supported Peppol countries — happy path.
    // Using each country's canonical format example from
    // config/einvoice.php ensures handlers can produce at
    // least one valid candidate.
    // ──────────────────────────────────────────────────────

    #[DataProvider('peppolBusinessCountryFixtures')]
    public function testBusinessClientRoutableAcrossPeppolCountries(int $countryId, string $countryCode, array $fields): void
    {
        $client = $this->makeClient(array_merge([
            'country_id' => $countryId,
            'classification' => 'business',
            'vat_number' => '',
            'id_number' => '',
        ], $fields));

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            "$countryCode business should pass validation with canonical fixtures. Errors: " . json_encode($result['client'] ?? [])
        );
    }

    public static function peppolBusinessCountryFixtures(): array
    {
        return [
            'AD' => [20,  'AD', ['vat_number' => 'ADA123456B']],
            'AT' => [40,  'AT', ['vat_number' => 'ATU12345678']],
            'BE' => [56,  'BE', ['vat_number' => 'BE0202239951']],
            'DK' => [208, 'DK', ['vat_number' => 'DK12345678']],
            'EE' => [233, 'EE', ['id_number'  => '12345678']],
            'FI' => [246, 'FI', ['id_number'  => '123456789012']],
            'DE' => [276, 'DE', ['vat_number' => 'DE123456789']],
            'IS' => [352, 'IS', ['id_number'  => '123456']],
            'LT' => [440, 'LT', ['id_number'  => '1234567']],
            'LU' => [442, 'LU', ['vat_number' => 'LU12345678']],
            'NL' => [528, 'NL', ['vat_number' => 'NL123456789B01']],
            'NO' => [578, 'NO', ['id_number'  => '123456789']],
            'PL' => [616, 'PL', ['vat_number' => 'PL1234567890']],
            'SE' => [752, 'SE', ['id_number'  => '1234567890']],
            'IE' => [372, 'IE', ['vat_number' => 'IE1A23456B']],
            'FR' => [250, 'FR', ['id_number'  => '123456789']], // 9 digits → FR:SIRENE
            'GR' => [300, 'GR', ['vat_number' => 'EL123456789']],
            'RO' => [642, 'RO', ['vat_number' => 'RO1234567890']],
            'SI' => [705, 'SI', ['vat_number' => 'SI12345678']],
            'ES' => [724, 'ES', ['vat_number' => 'ESA1234567B']],
            'GB' => [826, 'GB', ['vat_number' => 'GB123456789']],
            'PT' => [620, 'PT', ['vat_number' => 'PT123456789']],
        ];
    }

    // ──────────────────────────────────────────────────────
    // Non-deliverable countries that are in routing_rules
    // for tax metadata only (HR, CZ, HU, SK). Must be
    // blocked at the delivery-network stage.
    // ──────────────────────────────────────────────────────

    #[DataProvider('nonDeliverableCountryFixtures')]
    public function testNonDeliverableCountriesBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient([
            'country_id' => $countryId,
            'classification' => 'business',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue(
            $this->hasErrorForField($errors, 'classification'),
            "$countryCode must be flagged as undeliverable (in routing_rules but not a real Peppol destination). Errors: " . json_encode($errors)
        );
    }

    public static function nonDeliverableCountryFixtures(): array
    {
        return [
            'HR' => [191, 'HR'], // Croatia — has HR:VAT in routing_rules but not Peppol
            'CZ' => [203, 'CZ'], // Czech Republic
            'HU' => [348, 'HU'], // Hungary
            'SK' => [703, 'SK'], // Slovakia
            'CH' => [756, 'CH'], // Switzerland
        ];
    }
}

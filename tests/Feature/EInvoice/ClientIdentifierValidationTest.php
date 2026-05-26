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
            'routing_id' => '', // factory populates random digits; force empty for baseline.
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
    // Bad checkdigit — regex matches but mod-97 fails. Client-
    // level validation is lenient (format only); the check
    // digit is enforced strictly on the registration/send
    // path, not here.
    // ──────────────────────────────────────────────────────

    public function testBeBusinessInvalidCheckdigitPassesClientValidation(): void
    {
        // BE0202239951 is valid; mutating the last digit breaks the mod-97.
        // Format still matches, so client-level validation passes.
        $client = $this->makeClient(['vat_number' => 'BE0202239952']);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'Client-level validation must be lenient on the check digit. Errors: ' . json_encode($result['client'] ?? [])
        );
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
    // DE government — LWID may be supplied in any of
    // routing_id, id_number, or vat_number.
    // ──────────────────────────────────────────────────────

    public function testDeGovernmentLwidInIdNumberPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 276, // DE
            'classification' => 'government',
            'vat_number' => '',
            'id_number'  => '991-33333TEST-33',
            'routing_id' => '',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'DE government with LWID in id_number should pass. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testDeGovernmentLwidInVatNumberPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 276, // DE
            'classification' => 'government',
            'vat_number' => '991-33333TEST-33',
            'id_number'  => '',
            'routing_id' => '',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'DE government with LWID in vat_number should pass. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testDeGovernmentLwidInRoutingIdPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 276, // DE
            'classification' => 'government',
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '991-33333TEST-33',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'DE government with bare LWID in routing_id should pass. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testDeGovernmentWithNoIdentifierIsBlocked(): void
    {
        $client = $this->makeClient([
            'country_id' => 276, // DE
            'classification' => 'government',
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue(
            $this->hasErrorForField($errors, 'vat_number'),
            'DE government with no identifier should raise a vat_number error pointing at DE:LWID. Got: ' . json_encode($errors)
        );
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
            'FR' => [250, 'FR', ['id_number'  => '732829320']], // 9 digits → FR:SIRENE (Luhn-valid)
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

    // ──────────────────────────────────────────────────────
    // Explicit routing_id override (scheme:id form) is the
    // first thing checked — mirrors RoutingResolver at send
    // time. A valid routing_id overrides handler candidates.
    // ──────────────────────────────────────────────────────

    public function testValidGlnRoutingIdOverrideOnFrClient(): void
    {
        // FR handler reads id_number only; absent here. A valid GLN in
        // routing_id must still make the client routable.
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0088:1234567890128', // valid 13-digit GS1 GLN
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'FR client with valid GLN routing_id should pass. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testValidGlnRoutingIdOverrideOnBeClient(): void
    {
        // BE handler reads vat_number only; absent here. Valid GLN override passes.
        $client = $this->makeClient([
            'vat_number' => '',
            'routing_id' => '0088:1234567890128',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], 'BE client with valid GLN routing_id should pass. Errors: ' . json_encode($result['client'] ?? []));
    }

    public function testGlnRoutingIdTooShortGivesSpecificError(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0088:4334343',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'), 'Expected routing_id error. Got: ' . json_encode($errors));

        $label = $this->firstErrorLabel($errors, 'routing_id');
        $this->assertStringContainsStringIgnoringCase('0088:', $label);
        $this->assertStringContainsString('13', $label);
    }

    public function testBareNumericRoutingIdOnFrGivesGlnSpecificError(): void
    {
        // FR does not natively use routing_id. A bare numeric value is clearly
        // a GLN attempt — the error must SAY so, not fall through to the
        // generic "no valid routing identifier" message.
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '2435345543', // 10 digits, not a GLN
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'), 'Expected routing_id error for bare numeric on FR. Got: ' . json_encode($errors));

        $label = $this->firstErrorLabel($errors, 'routing_id');
        $this->assertStringContainsStringIgnoringCase('0088:', $label);
    }

    public function testBare13DigitRoutingIdOnFrRejectedRequires0088Prefix(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '1234567890128',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
        $this->assertStringContainsStringIgnoringCase('0088:', $this->firstErrorLabel($errors, 'routing_id'));
    }

    public function testVerifiedRealWorldGlnPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 250,
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0088:5401205000102',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], json_encode($result['client'] ?? []));
    }

    public function testGln0088PassesRegardlessOfTraditionalChecksumSemantics(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0088:1234567890129',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], json_encode($result['client'] ?? []));
    }

    public function testBareNumeric13DigitStillRequires0088Scheme(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '1234567890129',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
        $this->assertStringContainsStringIgnoringCase('0088:', $this->firstErrorLabel($errors, 'routing_id'));
    }

    public function testBareAlphanumericRoutingIdOnFrRejected(): void
    {
        // Non-numeric bare routing_id on FR is not a GLN and not in scheme:id
        // form. Give a clear error pointing to the expected format.
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => 'SUBM70N',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
        $this->assertStringContainsStringIgnoringCase('scheme:id', $this->firstErrorLabel($errors, 'routing_id'));
    }

    public function testBareRoutingIdOnItFallsThroughToHandler(): void
    {
        // IT B2B: bare routing_id is Codice Destinatario (CUUO); VAT + CUUO both required.
        $client = $this->makeClient([
            'country_id' => 380, // IT
            'classification' => 'business',
            'vat_number' => 'IT12345678901',
            'id_number'  => '',
            'routing_id' => 'SUBM70N',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertFalse($this->hasErrorForField($errors, 'routing_id'), 'IT bare routing_id must not trigger override error.');
        $this->assertFalse($this->hasErrorForField($errors, 'vat_number'), 'Valid IT VAT + CUUO should pass.');
    }

    public function testItBusinessMissingCuuoIsBlocked(): void
    {
        $client = $this->makeClient([
            'country_id' => 380,
            'classification' => 'business',
            'vat_number' => 'IT12345678901',
            'routing_id' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
    }

    public function testItBusinessMissingVatIsBlocked(): void
    {
        $client = $this->makeClient([
            'country_id' => 380,
            'classification' => 'business',
            'vat_number' => '',
            'routing_id' => 'SUBM70N',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'vat_number'));
    }

    public function testItDomesticConsumerRequiresCodiceFiscaleAndCuuo(): void
    {
        $originalCountryId = $this->testCompany->settings->country_id;
        $settings = $this->testCompany->settings;
        $settings->country_id = '380';
        $this->testCompany->settings = $settings;
        $this->testCompany->save();

        try {
            $client = $this->makeClient([
                'country_id' => 380,
                'classification' => 'individual',
                'id_number' => 'RSSMRA85M01H501Z',
                'vat_number' => '',
                'routing_id' => '',
            ]);

            $errors = $this->clientErrors($client);

            $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
        } finally {
            $settings = $this->testCompany->settings;
            $settings->country_id = $originalCountryId;
            $this->testCompany->settings = $settings;
            $this->testCompany->save();
        }
    }

    public function testItForeignConsumerRejectsPecEmail(): void
    {
        $client = $this->makeClient([
            'country_id' => 380,
            'classification' => 'individual',
            'id_number' => 'RSSMRA85M01H501Z',
            'vat_number' => '',
            'routing_id' => '',
        ]);

        $contact = $client->contacts()->where('is_primary', 1)->first();
        $contact->email = 'azienda@pec.it';
        $contact->save();

        $errors = $this->clientErrors($client->fresh());

        $this->assertTrue($this->hasErrorForField($errors, 'email'));
    }

    public function testRoutingIdEmptyIdAfterColonGivesSpecificError(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0088:',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertTrue($this->hasErrorForField($errors, 'routing_id'));
        $this->assertStringContainsStringIgnoringCase('scheme:id', $this->firstErrorLabel($errors, 'routing_id'));
    }

    public function testValidSgUenRoutingIdOverride(): void
    {
        // Another non-GLN ICD scheme: SG:UEN = 0195. Router has no regex for
        // "0195", so format validation degrades to non-empty — passes.
        $client = $this->makeClient([
            'country_id' => 702, // SG
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '0195:SGUENT08GA0028A',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue($result['passes'], 'SG client with valid SG:UEN override should pass. Errors: ' . json_encode($result['client'] ?? []));
    }

    // ──────────────────────────────────────────────────────
    // FR business — either id_number (SIREN/SIRET) OR a
    // VAT number (SIREN inferred from trailing 9 digits) is
    // sufficient. id_number must NOT be mandatory when VAT
    // is present.
    // ──────────────────────────────────────────────────────

    public function testFrBusinessWithVatNumberOnlyPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => 'FR44732829320', // trailing 9 = 732829320 (Luhn-valid SIREN)
            'id_number'  => '',
            'routing_id' => '',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'FR business with VAT only must pass (SIREN inferred from VAT). Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testFrBusinessWithIdNumberOnlyPasses(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '73282932000074', // Luhn-valid SIRET
            'routing_id' => '',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'FR business with id_number only must pass. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testFrBusinessLuhnInvalidButFormatValidIdentifierPassesClientValidation(): void
    {
        // 12345678900038 is a structurally valid 14-digit SIRET that fails the
        // Luhn check. Client-level validation is lenient (format only); the
        // Luhn check is enforced strictly on the registration/send path.
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '12345678900038',
            'routing_id' => '',
        ]);

        $result = (new EntityLevel())->checkClient($client);

        $this->assertTrue(
            $result['passes'],
            'FR client-level validation must be lenient on the SIREN/SIRET check digit. Errors: ' . json_encode($result['client'] ?? [])
        );
    }

    public function testFrBusinessWithNeitherIdNumberNorVatIsBlocked(): void
    {
        $client = $this->makeClient([
            'country_id' => 250, // FR
            'vat_number' => '',
            'id_number'  => '',
            'routing_id' => '',
        ]);

        $errors = $this->clientErrors($client);

        $this->assertNotEmpty($errors, 'FR business with neither identifier must be blocked');
    }
}

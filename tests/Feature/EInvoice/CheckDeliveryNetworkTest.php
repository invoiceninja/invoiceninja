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
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CheckDeliveryNetworkTest extends TestCase
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

    private function makeClient(int $countryId, string $classification): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->testCompany->id,
            'country_id' => $countryId,
            'classification' => $classification,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->testCompany->id,
            'is_primary' => 1,
            'email' => 'test@example.com',
        ]);

        return $client->fresh();
    }

    // ────────────────────────────���──────────────────────���──
    // No country set
    // ───────────────────────────��──────────────────────────

    public function testNoCountryReturnsError(): void
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->testCompany->id,
            'country_id' => null,
            'classification' => 'business',
        ]);

        $result = $client->fresh()->checkDeliveryNetwork();
        $this->assertIsString($result);
        $this->assertStringContainsString('country', strtolower($result));
    }

    // ───────────────────────���─────────────────────���────────
    // Peppol Business Countries — B+G routable, C blocked
    // AT, BE, DK, EE, FI, DE, IS, LT, LU, NL, NO, SE, IE
    // ──────────────────────────────────��───────────────────

    #[DataProvider('peppolBusinessCountryProvider')]
    public function testBusinessCountryBusinessIsRoutable(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'business');
        $this->assertNull($client->checkDeliveryNetwork(), "$countryCode business should be routable");
    }

    #[DataProvider('peppolBusinessCountryProvider')]
    public function testBusinessCountryGovernmentIsRoutable(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'government');
        $this->assertNull($client->checkDeliveryNetwork(), "$countryCode government should be routable");
    }

    #[DataProvider('peppolBusinessCountryProvider')]
    public function testBusinessCountryIndividualIsBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'individual');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "$countryCode individual should be blocked");
        $this->assertStringContainsStringIgnoringCase('individual', $result);
    }

    public static function peppolBusinessCountryProvider(): array
    {
        return [
            'AT' => [40, 'AT'],
            'BE' => [56, 'BE'],
            'DK' => [208, 'DK'],
            'EE' => [233, 'EE'],
            'FI' => [246, 'FI'],
            'DE' => [276, 'DE'],
            'IS' => [352, 'IS'],
            'LT' => [440, 'LT'],
            'LU' => [442, 'LU'],
            'NL' => [528, 'NL'],
            'NO' => [578, 'NO'],
            'SE' => [752, 'SE'],
            'IE' => [372, 'IE'],
        ];
    }

    // ───────────────────────────────────���──────────────────
    // Peppol Government Countries — G routable, C blocked
    // FR, GR, PT, RO, SI, ES, GB
    // ──────────────────────────────────────────────────────

    #[DataProvider('peppolGovernmentCountryProvider')]
    public function testGovernmentCountryGovernmentIsRoutable(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'government');
        $this->assertNull($client->checkDeliveryNetwork(), "$countryCode government should be routable");
    }

    #[DataProvider('peppolGovernmentCountryProvider')]
    public function testGovernmentCountryIndividualIsBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'individual');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "$countryCode individual should be blocked");
    }

    public static function peppolGovernmentCountryProvider(): array
    {
        return [
            'FR' => [250, 'FR'],
            'GR' => [300, 'GR'],
            'PT' => [620, 'PT'],
            'RO' => [642, 'RO'],
            'SI' => [705, 'SI'],
        ];
    }

    // ──────────────────────────────────────────────────────
    // ES and GB — business in routing rules
    // ─────────────────────────────────��────────────────────

    public function testEsBusinessIsRoutable(): void
    {
        $client = $this->makeClient(724, 'business');
        $this->assertNull($client->checkDeliveryNetwork(), "ES business should be routable");
    }

    public function testGbBusinessIsRoutable(): void
    {
        $client = $this->makeClient(826, 'business');
        $this->assertNull($client->checkDeliveryNetwork(), "GB business should be routable");
    }

    public function testEsGovernmentIsBlocked(): void
    {
        $client = $this->makeClient(724, 'government');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "ES government should be blocked - routing rules only support B");
    }

    public function testGbGovernmentIsBlocked(): void
    {
        $client = $this->makeClient(826, 'government');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "GB government should be blocked - routing rules only support B");
    }

    // ───���──────────────────────────────────────────────────
    // Unsupported countries — all classifications blocked
    // ───────────────────��─────────────────────────────��────

    #[DataProvider('unsupportedCountryProvider')]
    public function testUnsupportedCountryBusinessIsBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'business');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "$countryCode business should be blocked");
    }

    #[DataProvider('unsupportedCountryProvider')]
    public function testUnsupportedCountryGovernmentIsBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'government');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "$countryCode government should be blocked");
    }

    #[DataProvider('unsupportedCountryProvider')]
    public function testUnsupportedCountryIndividualIsBlocked(int $countryId, string $countryCode): void
    {
        $client = $this->makeClient($countryId, 'individual');
        $result = $client->checkDeliveryNetwork();
        $this->assertIsString($result, "$countryCode individual should be blocked");
    }

    public static function unsupportedCountryProvider(): array
    {
        return [
            'BR' => [76, 'BR'],
            'CN' => [156, 'CN'],
        ];
    }

    // ─────���──────────────────────��─────────────────────────
    // IT — commented out of peppol_business_countries
    // ───────────────────────────���──────────────────────────

    public function testItBusinessIsRoutable(): void
    {
        $client = $this->makeClient(380, 'business');
        $this->assertNull($client->checkDeliveryNetwork(), "IT business should be routable via SDI");
    }

    public function testItGovernmentIsRoutable(): void
    {
        $client = $this->makeClient(380, 'government');
        $this->assertNull($client->checkDeliveryNetwork(), "IT government should be routable via SDI");
    }

    public function testItIndividualIsRoutable(): void
    {
        $client = $this->makeClient(380, 'individual');
        $this->assertNull($client->checkDeliveryNetwork(), "IT individual should be routable via SDI");
    }

    // ──────────────��───────────────────────���───────────────
    // Null classification — must NOT silently pass
    // ──────────────────────────��───────────────────────────

    public function testNullClassificationBeIsClassedAsBusiness(): void
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->testCompany->id,
            'country_id' => 56, // BE
            'classification' => null,
        ]);

        $result = $client->fresh()->checkDeliveryNetwork();
        $this->assertNull($result);
    }

    public function testNullClassificationDeIsClassedAsBusiness(): void
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->testCompany->id,
            'country_id' => 276, // DE
            'classification' => null,
        ]);

        $result = $client->fresh()->checkDeliveryNetwork();
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────────
    // peppolSendingEnabled cross-check
    // ���────────────���────────────────────────────────────────

    public function testPeppolSendingEnabledFalseForIndividualBe(): void
    {
        $client = $this->makeClient(56, 'individual');
        $this->assertFalse($client->peppolSendingEnabled(), "BE individual peppolSendingEnabled should be false");
    }

    public function testPeppolSendingEnabledFalseForUnsupportedCountry(): void
    {
        $client = $this->makeClient(840, 'business');
        $this->assertFalse($client->peppolSendingEnabled(), "US business peppolSendingEnabled should be false");
    }
}

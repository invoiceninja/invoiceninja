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

namespace Tests\Unit\Tax;

use App\Services\Tax\VatNumberCheck;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 *  App\Services\Tax\VatNumberCheck
 */
class VatNumberTest extends TestCase
{
    /**
     * GitHub Actions sets NINJA_ENVIRONMENT=hosted. These cases exercise the
     * VIES client unless a test opts into the hosted proxy.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.environment' => 'selfhost']);
        Http::preventStrayRequests();
    }

    public function testVatNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'IE', 'vatNumber' => '1234567L', 'valid' => false, 'name' => '---', 'address' => '---'])]);

        $vat_checker = new VatNumberCheck("1234567L", "IE");
        $result = $vat_checker->run();

        $this->assertFalse($result->isValid());
    }

    public function testValidVatNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'AT', 'vatNumber' => 'U12345678', 'valid' => true, 'name' => 'Example GmbH', 'address' => 'Example Street 1, 1010 Wien'])]);

        $result = (new VatNumberCheck("ATU 123 456 78", "AT"))->run();

        $this->assertTrue($result->isValid());
        $this->assertEquals('Example GmbH', $result->getName());

        Http::assertSent(fn ($request) => $request['countryCode'] == 'AT' && $request['vatNumber'] == 'U12345678');
    }

    public function testGreeceIsCheckedAsEl()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['countryCode' => 'EL', 'vatNumber' => '123456789', 'valid' => false])]);

        (new VatNumberCheck("GR123456789", "GR"))->run();

        Http::assertSent(fn ($request) => $request['countryCode'] == 'EL' && $request['vatNumber'] == '123456789');
    }

    #[DataProvider('vatCountryProvider')]
    public function testVatCountrySelection(string $vat_number, string $fallback_country, string $expected_country, string $expected_number)
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['valid' => false])]);

        (new VatNumberCheck($vat_number, $fallback_country))->run();

        Http::assertSent(fn ($request) => $request['countryCode'] == $expected_country && $request['vatNumber'] == $expected_number);
    }

    public static function vatCountryProvider(): array
    {
        return [
            'prefix overrides country' => ['de 123 456 789', 'FR', 'DE', '123456789'],
            'unprefixed number uses fallback' => ['123456789', 'FR', 'FR', '123456789'],
            'Greek GR prefix' => ['GR123456789', 'DE', 'EL', '123456789'],
            'Greek EL prefix' => ['EL123456789', 'DE', 'EL', '123456789'],
            'Greek fallback' => ['123456789', 'GR', 'EL', '123456789'],
            'Austrian prefix preserves letter' => ['ATU12345678', 'FR', 'AT', 'U12345678'],
            'unprefixed Austrian number' => ['U12345678', 'AT', 'AT', 'U12345678'],
            'unprefixed French letters' => ['AB123456789', 'FR', 'FR', 'AB123456789'],
            'Northern Ireland prefix' => ['XI123456789', 'GB', 'XI', '123456789'],
        ];
    }

    public function testUnavailableMemberStateIsNotAnInvalidNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response(['actionSucceed' => false, 'errorWrappers' => [['error' => 'MS_UNAVAILABLE']]])]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck("1234567L", "IE"))->run();
    }

    public function testRateLimitPageIsNotAnInvalidNumber()
    {
        Http::fake(['ec.europa.eu/*' => Http::response('<html><body>Access denied</body></html>', 403)]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck("1234567L", "IE"))->run();
    }

    #[DataProvider('vatCountryProvider')]
    public function testHostedApiUsesTheSamePreparation(string $vat_number, string $fallback_country, string $expected_country, string $expected_number)
    {
        config(['ninja.environment' => 'hosted']);

        Http::fake(['https://vat.businesspress.io/api/vat/validation/validate' => Http::response([
            'success' => true,
            'data' => ['valid' => false],
        ])]);

        (new VatNumberCheck($vat_number, $fallback_country))->run();

        Http::assertSent(fn ($request) => $request->url() === 'https://vat.businesspress.io/api/vat/validation/validate'
            && $request['country_code'] == $expected_country
            && $request['vat_number'] == $expected_number);
    }

    public function testHostedValidNumberSetsNameAndAddress()
    {
        config(['ninja.environment' => 'hosted']);

        Http::fake(['https://vat.businesspress.io/api/vat/validation/validate' => Http::response([
            'success' => true,
            'data' => ['valid' => true, 'name' => 'Example GmbH', 'address' => 'Example Street 1'],
        ])]);

        $result = (new VatNumberCheck('ATU12345678', 'AT'))->run();

        $this->assertTrue($result->isValid());
        $this->assertEquals('Example GmbH', $result->getName());
        $this->assertEquals('Example Street 1', $result->getAddress());
    }

    public function testHostedExplicitInvalidNumber()
    {
        config(['ninja.environment' => 'hosted']);

        Http::fake(['https://vat.businesspress.io/api/vat/validation/validate' => Http::response([
            'success' => true,
            'data' => ['valid' => false],
        ])]);

        $this->assertFalse((new VatNumberCheck('1234567L', 'IE'))->run()->isValid());
    }

    public function testHostedHttpFailureIsNotAnInvalidNumber()
    {
        config(['ninja.environment' => 'hosted']);

        Http::fake(['https://vat.businesspress.io/api/vat/validation/validate' => Http::response('unavailable', 500)]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck('1234567L', 'IE'))->run();
    }

    public function testHostedBodyWithoutValidIsNotAnInvalidNumber()
    {
        config(['ninja.environment' => 'hosted']);

        Http::fake(['https://vat.businesspress.io/api/vat/validation/validate' => Http::response(['success' => false])]);

        $this->expectException(\RuntimeException::class);

        (new VatNumberCheck('1234567L', 'IE'))->run();
    }

    public function testEmptyVatNumberIsNotSent()
    {
        Http::fake();

        $this->assertFalse((new VatNumberCheck(null, "IE"))->run()->isValid());

        Http::assertNothingSent();
    }
}

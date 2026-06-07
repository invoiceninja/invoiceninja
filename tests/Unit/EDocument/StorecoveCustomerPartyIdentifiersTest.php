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

namespace Tests\Unit\EDocument;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use PHPUnit\Framework\Attributes\DataProvider;

class StorecoveCustomerPartyIdentifiersTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    /**
     * @param  array<int, mixed>  $rule
     * @param  array<int, array{scheme: string, id: string}>  $expectedPairs
     */
    #[DataProvider('storecovePublicIdentifierCases')]
    public function testStorecovePublicIdentifiersResolveForEveryConfiguredRoutingRule(
        string $country,
        string $classification,
        array $rule,
        array $expectedPairs,
    ): void {
        $client = $this->fakeClientForPublicIdentifierRule($country, $classification, $rule);
        $invoice = (object) ['client' => $client];

        $pairs = CountryFactory::make($country)
            ->storecoveCustomerPartyPublicIdentifiers($client, $invoice, new StorecoveRouter());

        $debugContext = $this->storecovePublicIdentifierDebugContext($country, $classification, $rule, $client, $expectedPairs, $pairs);
        $debugMessage = json_encode($debugContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: var_export($debugContext, true);

        // nlog($debugContext);

        $this->assertSame($expectedPairs, $pairs, $debugMessage);
    }

    /**
     * @param  array<int, mixed>  $rule
     * @param  array<int, array{scheme: string, id: string}>  $expectedPairs
     * @param  array<int, array{scheme: string, id: string}>  $actualPairs
     * @return array{
     *   country: string,
     *   classification: string,
     *   starting_values: array{routing_rule: array<int, mixed>, vat_number: mixed, id_number: mixed, routing_id: mixed},
     *   expected: array<int, array{scheme: string, id: string}>,
     *   actual: array<int, array{scheme: string, id: string}>
     * }
     */
    private function storecovePublicIdentifierDebugContext(
        string $country,
        string $classification,
        array $rule,
        object $client,
        array $expectedPairs,
        array $actualPairs,
    ): array {
        return [
            'country' => $country,
            'classification' => $classification,
            'starting_values' => [
                'routing_rule' => $rule,
                'vat_number' => $client->vat_number ?? '',
                'id_number' => $client->id_number ?? '',
                'routing_id' => $client->routing_id ?? '',
            ],
            'expected' => $expectedPairs,
            'actual' => $actualPairs,
        ];
    }

    /**
     * @return array<string, array{
     *   country: string,
     *   classification: string,
     *   rule: array<int, mixed>,
     *   expectedPairs: array<int, array{scheme: string, id: string}>
     * }>
     */
    public static function storecovePublicIdentifierCases(): array
    {
        $config = require dirname(__DIR__, 3) . '/config/einvoice.php';
        $cases = [];

        foreach ($config['routing_rules'] as $country => $rules) {
            foreach (self::routingRows($rules) as $rule) {
                foreach (self::classificationsForRule($rule) as $classification) {
                    $expectedPairs = self::expectedPublicIdentifierPairs($country, $classification, $rule);

                    if ($expectedPairs === []) {
                        continue;
                    }

                    $cases["{$country} {$classification}"] = [
                        'country' => $country,
                        'classification' => $classification,
                        'rule' => $rule,
                        'expectedPairs' => $expectedPairs,
                    ];
                }
            }
        }

        return $cases;
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return array<int, array<int, mixed>>
     */
    private static function routingRows(array $rules): array
    {
        return isset($rules[0]) && is_array($rules[0]) ? $rules : [$rules];
    }

    /**
     * @param  array<int, mixed>  $rule
     * @return string[]
     */
    private static function classificationsForRule(array $rule): array
    {
        $classifications = [];
        $code = (string) ($rule[0] ?? '');

        if (str_contains($code, 'B')) {
            $classifications[] = 'business';
        }

        if (str_contains($code, 'G')) {
            $classifications[] = 'government';
        }

        if (str_contains($code, 'C')) {
            $classifications[] = 'individual';
        }

        return $classifications;
    }

    /**
     * @param  array<int, mixed>  $rule
     * @return array<int, array{scheme: string, id: string}>
     */
    private static function expectedPublicIdentifierPairs(string $country, string $classification, array $rule): array
    {
        $legalScheme = self::schemeValue($rule[1] ?? '');
        $taxScheme = self::schemeValue($rule[2] ?? '');
        $routingScheme = self::schemeValue($rule[3] ?? '');

        $primary = self::expectedPrimaryPublicIdentifierPair($country, $classification, $legalScheme, $taxScheme, $routingScheme);

        if ($primary === null) {
            return [];
        }

        $pairs = [$primary];

        if ($taxScheme !== '' && $taxScheme !== $primary['scheme']) {
            $pairs[] = [
                'scheme' => $taxScheme,
                'id' => self::expectedIdentifierFor($taxScheme),
            ];
        }

        return $pairs;
    }

    /**
     * @return array{scheme: string, id: string}|null
     */
    private static function expectedPrimaryPublicIdentifierPair(
        string $country,
        string $classification,
        string $legalScheme,
        string $taxScheme,
        string $routingScheme,
    ): ?array {
        if ($routingScheme === '') {
            return null;
        }

        if ($routingScheme === 'Email') {
            return $taxScheme === ''
                ? null
                : ['scheme' => $taxScheme, 'id' => self::expectedIdentifierFor($taxScheme)];
        }

        if (preg_match('/^(\d{4}):(.+)$/', $routingScheme, $matches)) {
            if ($country === 'AT' && $classification === 'government') {
                return ['scheme' => 'AT:GOV', 'id' => 'b'];
            }

            return $legalScheme === ''
                ? null
                : ['scheme' => $legalScheme, 'id' => self::expectedIdentifierFor($legalScheme, $matches[2])];
        }

        if ($routingScheme === 'GLN' || str_contains($routingScheme, ':CUUO')) {
            return ['scheme' => $routingScheme, 'id' => self::expectedIdentifierFor($routingScheme)];
        }

        // Compound routing scheme (e.g. "FR:SIRENE or FR:SIRET") is resolved to
        // the concrete atomic scheme whose format matches the identifier - the
        // compound literal must never be emitted as a publicIdentifier scheme.
        if (str_contains($routingScheme, ' or ')) {
            $id = self::expectedIdentifierFor($routingScheme);
            $atomicSchemes = array_map('trim', explode(' or ', $routingScheme));

            // Data providers run before the Laravel app boots, so the
            // config()-backed regex is unavailable - pass it explicitly.
            $config = require dirname(__DIR__, 3) . '/config/einvoice.php';
            $validator = new StorecoveIdentifierValidator($config['identifier_regex']);

            foreach ($atomicSchemes as $atomicScheme) {
                if ($validator->matchesSchemeFormat($atomicScheme, $id)) {
                    return ['scheme' => $atomicScheme, 'id' => $id];
                }
            }

            return ['scheme' => $atomicSchemes[0], 'id' => $id];
        }

        return [
            'scheme' => $routingScheme,
            'id' => self::expectedIdentifierFor($routingScheme),
        ];
    }

    /**
     * @param  array<int, mixed>  $rule
     */
    private function fakeClientForPublicIdentifierRule(string $country, string $classification, array $rule): object
    {
        $legalScheme = self::schemeValue($rule[1] ?? '');
        $taxScheme = self::schemeValue($rule[2] ?? '');
        $routingScheme = self::schemeValue($rule[3] ?? '');

        $idNumber = $legalScheme !== '' ? self::identifierFixtureFor($legalScheme) : '';
        $vatNumber = $taxScheme !== '' ? self::identifierFixtureFor($taxScheme) : '';
        $routingId = '';

        if ($routingScheme === 'GLN' || str_contains($routingScheme, ':CUUO')) {
            $routingId = self::identifierFixtureFor($routingScheme);
        }

        if ($routingScheme === 'Email' && $taxScheme === 'IT:CF') {
            $idNumber = self::identifierFixtureFor($taxScheme);
            $vatNumber = '';
        }

        if ($legalScheme === ''
            && $routingScheme !== 'Email'
            && !self::taxLikeScheme($routingScheme)
            && !self::fixedEndpointScheme($routingScheme)) {
            $idNumber = self::identifierFixtureFor($routingScheme);
        }

        if ($taxScheme === '' && self::taxLikeScheme($routingScheme)) {
            $vatNumber = self::identifierFixtureFor($routingScheme);
        }

        return (object) [
            'country' => (object) ['iso_3166_2' => $country],
            'classification' => $classification,
            'vat_number' => $vatNumber,
            'id_number' => $idNumber,
            'routing_id' => $routingId,
        ];
    }

    private static function schemeValue(mixed $scheme): string
    {
        return !empty($scheme) ? (string) $scheme : '';
    }

    private static function fixedEndpointScheme(string $scheme): bool
    {
        return (bool) preg_match('/^\d{4}:.+$/', $scheme);
    }

    private static function taxLikeScheme(string $scheme): bool
    {
        return str_contains($scheme, ':VAT')
            || str_contains($scheme, ':IVA')
            || str_contains($scheme, ':CF');
    }

    private static function expectedIdentifierFor(string $scheme, ?string $fallback = null): string
    {
        $identifier = self::identifierFixtureFor($scheme, $fallback);

        if ($scheme === 'DK:DIGST') {
            $cleanIdentifier = preg_replace("/[^a-zA-Z0-9]/", "", $identifier) ?? '';

            return preg_replace('/^DK/i', '', $cleanIdentifier) ?? $cleanIdentifier;
        }

        return $identifier;
    }

    private static function identifierFixtureFor(string $scheme, ?string $fallback = null): string
    {
        $examples = require dirname(__DIR__, 3) . '/config/einvoice.php';
        $examples = $examples['identifier_format_examples'];

        if (isset($examples[$scheme])) {
            return $examples[$scheme];
        }

        if (str_contains($scheme, ' or ')) {
            $atomicScheme = trim(explode(' or ', $scheme)[0]);

            return self::identifierFixtureFor($atomicScheme, $fallback);
        }

        return match ($scheme) {
            'DUNS, GLN, LEI' => '123456789',
            'GLN' => '1234567890123',
            'AT:GOV' => 'b',
            'DE:LWID' => '123-ABC-12',
            default => $fallback ?? '123456789',
        };
    }

    /**
     * Prefixed Belgian enterprise number on id_number must emit bare digits for BE:EN (Storecove canonical form).
     */
    public function testBelgiumPrefixedIdNumberReturnsBareTenDigitsForBeEn(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $be = Country::where('iso_3166_2', 'BE')->first();
        $this->assertNotNull($be);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $be->id,
            'classification' => 'business',
            'vat_number' => '',
            'id_number' => 'BE0202239951',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice->load('client.country');

        $router = new StorecoveRouter();

        $pairs = CountryFactory::make('BE')->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertNotEmpty($pairs);
        $this->assertSame('BE:EN', $pairs[0]['scheme']);
        $this->assertSame('0202239951', $pairs[0]['id']);
    }

    /**
     * Belgium supplier EndpointID (resolveCompanyScheme): strip leading BE country prefix from VAT/id value.
     */
    public function testBelgiumResolveCompanySchemeStripsLeadingBeFromEndpointId(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $settings = $company->settings;
        $settings->vat_number = 'BE 0123.456.789';
        $company->settings = $settings;

        $resolved = CountryFactory::make('BE')->resolveEndpointScheme($company);

        $this->assertSame('0208', $resolved['scheme']);
        $this->assertSame('0123456789', $resolved['id']);
    }

    /**
     * NL business: document primary follows legacy routing column (NL:VAT + BTW) when only VAT is set.
     */
    public function testNlBusinessUsesVatAsPrimaryPublicIdentifier(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $nl = Country::where('iso_3166_2', 'NL')->first();
        $this->assertNotNull($nl);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $nl->id,
            'classification' => 'business',
            'vat_number' => 'NL123456789B01',
            'id_number' => '',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice->load('client.country');

        $router = new StorecoveRouter();

        $pairs = CountryFactory::make('NL')->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertNotEmpty($pairs);
        $this->assertSame('NL:VAT', $pairs[0]['scheme']);
        $this->assertSame('NL123456789B01', $pairs[0]['id']);
    }

    /**
     * NL matrix: legal column NL:KVK, routing/tax NL:VAT — when both KVK and VAT are present,
     * primary document line follows routing (NL:VAT), not the legal-id column alone.
     */
    public function testNlBusinessWithKvkAndVatUsesRoutingColumnAsPrimaryPublicIdentifier(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $nl = Country::where('iso_3166_2', 'NL')->first();
        $this->assertNotNull($nl);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $nl->id,
            'classification' => 'business',
            'vat_number' => 'NL123456789B01',
            'id_number' => '12345678',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice->load('client.country');

        $router = new StorecoveRouter();

        $pairs = CountryFactory::make('NL')->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertCount(1, $pairs);
        $this->assertSame('NL:VAT', $pairs[0]['scheme']);
        $this->assertSame('NL123456789B01', $pairs[0]['id']);
    }

    /**
     * FI: primary routing/legal scheme FI:OVT plus tax scheme FI:VAT — two canonical Storecove lines when both values match formats.
     */
    public function testFinlandOvtAndVatProduceDualPublicIdentifierLines(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $fi = Country::where('iso_3166_2', 'FI')->first();
        $this->assertNotNull($fi);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $fi->id,
            'classification' => 'business',
            'vat_number' => 'FI12345678',
            'id_number' => '003712345678',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice->load('client.country');

        $router = new StorecoveRouter();

        $pairs = CountryFactory::make('FI')->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertCount(2, $pairs);
        $this->assertSame('FI:OVT', $pairs[0]['scheme']);
        $this->assertSame('003712345678', $pairs[0]['id']);
        $this->assertSame('FI:VAT', $pairs[1]['scheme']);
        $this->assertSame('FI12345678', $pairs[1]['id']);
    }

    /**
     * Belgium: first routing discovery candidate (getCandidates) matches primary Storecove document pair.
     */
    public function testBelgiumFirstRoutingCandidateMatchesPrimaryDocumentPublicIdentifier(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $be = Country::where('iso_3166_2', 'BE')->first();
        $this->assertNotNull($be);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $be->id,
            'classification' => 'business',
            'vat_number' => '',
            'id_number' => 'BE0202239951',
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice->load('client.country');

        $router = new StorecoveRouter();

        $handler = CountryFactory::make('BE');
        $routingCandidates = $handler->getCandidates($invoice->client, 'business', $router);
        $documentPairs = $handler->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertNotEmpty($routingCandidates);
        $this->assertNotEmpty($documentPairs);
        $this->assertSame($routingCandidates[0]['scheme'], $documentPairs[0]['scheme']);
        $this->assertSame($routingCandidates[0]['id'], $documentPairs[0]['id']);
    }

    /**
     * BaseCountry::resolveClientEndpointScheme: an unconfigured client (no VAT, no id_number,
     * no routing_id, non-email country) returns empty scheme + empty id so Peppol validation
     * surfaces the misconfiguration (BR-CL-25 / PEPPOL-EN16931-CL008).
     */
    public function testBaseClientEndpointSchemeFallsBackToEmptyForUnsetClient(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $de = Country::where('iso_3166_2', 'DE')->first();
        $this->assertNotNull($de);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $de->id,
            'classification' => 'business',
            'vat_number' => '',
            'id_number' => '',
            'routing_id' => '',
        ]);

        $client->load('country');

        $router = new StorecoveRouter();

        $resolved = CountryFactory::make('DE')->resolveClientEndpointScheme($client, $router);

        $this->assertSame('', $resolved['scheme']);
        $this->assertSame('', $resolved['id']);
    }

    /**
     * BaseCountry::resolveClientEndpointScheme: when the country handler returns getCandidates,
     * the first pair becomes the EndpointID scheme + id (with friendly scheme converted to ICD).
     */
    public function testBaseClientEndpointSchemeUsesGetCandidatesFirstPair(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $be = Country::where('iso_3166_2', 'BE')->first();
        $this->assertNotNull($be);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $be->id,
            'classification' => 'business',
            'vat_number' => 'BE0202239951',
            'id_number' => '',
            'routing_id' => '',
        ]);

        $client->load('country');

        $router = new StorecoveRouter();

        $resolved = CountryFactory::make('BE')->resolveClientEndpointScheme($client, $router);

        $this->assertSame('0208', $resolved['scheme']);
        $this->assertSame('0202239951', $resolved['id']);
    }

    /**
     * BE::resolveClientPartyIdentificationScheme mirrors the EndpointID scheme + value
     * (0208 + 10-digit Enterprise Number) for buyer PartyIdentification consistency.
     */
    public function testBeClientPartyIdentificationSchemeMirrorsEndpoint(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $be = Country::where('iso_3166_2', 'BE')->first();
        $this->assertNotNull($be);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $be->id,
            'classification' => 'business',
            'vat_number' => 'BE 0202.239.951',
            'id_number' => '',
        ]);

        $client->load('country');

        $resolved = CountryFactory::make('BE')->resolveClientPartyIdentificationScheme($client);

        $this->assertNotNull($resolved);
        $this->assertSame('0208', $resolved['scheme']);
        $this->assertSame('0202239951', $resolved['id']);
    }

    /**
     * Email-routed countries (IN, SA, IT B2C) emit EAS 0202 with VAT / id_number / email fallback,
     * keeping the EndpointID a valid EAS code with a deliverable value.
     */
    public function testIndiaClientEndpointSchemeReturns0202WithGstinFallback(): void
    {
        $this->makeTestData();

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $in = Country::where('iso_3166_2', 'IN')->first();
        $this->assertNotNull($in);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'country_id' => $in->id,
            'classification' => 'business',
            'vat_number' => '29ABCDE1234F1Z5',
            'id_number' => '',
            'routing_id' => '',
        ]);

        $client->load('country');

        $router = new StorecoveRouter();

        $resolved = CountryFactory::make('IN')->resolveClientEndpointScheme($client, $router);

        $this->assertSame('0202', $resolved['scheme']);
        $this->assertSame('29ABCDE1234F1Z5', $resolved['id']);
    }
}

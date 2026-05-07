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
use App\Services\EDocument\Standards\Peppol\CountryFactory;

class StorecoveCustomerPartyIdentifiersTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

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
        $router->setInvoice($invoice);

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
        $router->setInvoice($invoice);

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
        $router->setInvoice($invoice);

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
        $router->setInvoice($invoice);

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
        $router->setInvoice($invoice);

        $handler = CountryFactory::make('BE');
        $routingCandidates = $handler->getCandidates($invoice->client, 'business', $router);
        $documentPairs = $handler->storecoveCustomerPartyPublicIdentifiers($invoice->client, $invoice, $router);

        $this->assertNotEmpty($routingCandidates);
        $this->assertNotEmpty($documentPairs);
        $this->assertSame($routingCandidates[0]['scheme'], $documentPairs[0]['scheme']);
        $this->assertSame($routingCandidates[0]['id'], $documentPairs[0]['id']);
    }
}

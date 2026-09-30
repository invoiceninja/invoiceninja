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

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\AppLink;
use Tests\TestCase;

/**
 * The server's own record links — invoiceninja/flutter#144.
 *
 * @see \App\Utils\AppLink
 */
class AppLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.app_url' => 'https://example.test']);
    }

    /**
     * Unsaved, so this stays a unit test: `hashed_id` is derived from `id` by
     * MakesHash, and `setRelation` hands over the company without a query.
     */
    private function client(bool $with_company = true): Client
    {
        $client = new Client();
        $client->id = 1;

        if ($with_company) {
            $company = new Company();
            $company->id = 2;
            $client->setRelation('company', $company);
        }

        return $client;
    }

    public function testARecordLink()
    {
        $client = $this->client();

        $this->assertSame(
            "https://example.test/app/clients/{$client->hashed_id}?company={$client->company->hashed_id}",
            AppLink::forRecord('clients', $client)
        );
    }

    public function testNoEditSuffix()
    {
        // React gets its suffix at the bridge; adding one here means `/edit/edit`.
        $invoice = new Invoice();
        $invoice->id = 1;

        $this->assertStringNotContainsString('/edit', (string) AppLink::forRecord('invoices', $invoice));
    }

    public function testACompanylessLinkCarriesNoQuery()
    {
        $client = $this->client(with_company: false);

        $this->assertSame(
            "https://example.test/app/clients/{$client->hashed_id}",
            AppLink::forRecord('clients', $client)
        );
    }

    public function testTheTrailingSlashOnAConfiguredHostIsNotDoubled()
    {
        config(['ninja.app_url' => 'https://example.test/']);
        $client = $this->client(with_company: false);

        $this->assertSame(
            "https://example.test/app/clients/{$client->hashed_id}",
            AppLink::forRecord('clients', $client)
        );
    }
}

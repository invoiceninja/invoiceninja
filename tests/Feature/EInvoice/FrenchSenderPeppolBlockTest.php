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

use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class FrenchSenderPeppolBlockTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function sendingCapableCompany(int $countryId): Company
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'legal_entity_id' => 12345,
        ]);

        $settings = $company->settings;
        $settings->country_id = (string) $countryId;
        $company->settings = $settings;

        $tax_data = $company->tax_data ?: new \stdClass();
        $tax_data->acts_as_sender = true;
        $company->tax_data = $tax_data;
        $company->save();

        $this->account->e_invoice_quota = 100;
        $this->account->is_flagged = false;
        $this->account->save();

        return $company->fresh();
    }

    public function testFrenchSenderCannotSendOverPeppol(): void
    {
        /** 250 = FR */
        $company = $this->sendingCapableCompany(250);

        $this->assertFalse($company->peppolSendingEnabled());
    }

    public function testNonFrenchSenderWithIdenticalSetupCanSend(): void
    {
        /** 276 = DE */
        $company = $this->sendingCapableCompany(276);

        $this->assertTrue($company->peppolSendingEnabled());
    }
}

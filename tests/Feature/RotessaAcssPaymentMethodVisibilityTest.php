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

namespace Tests\Feature;

use App\Livewire\PaymentMethodsTable;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Livewire\Livewire;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Regression coverage for the client portal "Add payment method" button
 * disappearing when a client/group is restricted to a Rotessa (ACSS) gateway only.
 *
 * The portal button wrapper in
 * resources/views/portal/ninja2020/components/livewire/payment-methods-table.blade.php
 * must show whenever any of the inner gateway links would render. For a Rotessa-only
 * CAD client that means getACSSGateway() resolves the gateway even though
 * getBankTransferGateway() (no argument) intentionally excludes Rotessa.
 */
class RotessaAcssPaymentMethodVisibilityTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private const ROTESSA_GATEWAY_KEY = '91be24c7b792230bced33e930ac61676';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        Model::reguard();

        $this->makeTestData();

        CompanyGateway::query()->withTrashed()->cursor()->each(function ($cg) {
            $cg->forceDelete();
        });

        $fees_and_limits = [];
        $fees_and_limits[GatewayType::ACSS]['min_limit'] = -1;
        $fees_and_limits[GatewayType::ACSS]['max_limit'] = -1;
        $fees_and_limits[GatewayType::ACSS]['fee_amount'] = 0;
        $fees_and_limits[GatewayType::ACSS]['fee_percent'] = 0;
        $fees_and_limits[GatewayType::ACSS]['fee_tax_name1'] = '';
        $fees_and_limits[GatewayType::ACSS]['fee_tax_rate1'] = 0;
        $fees_and_limits[GatewayType::ACSS]['fee_tax_name2'] = '';
        $fees_and_limits[GatewayType::ACSS]['fee_tax_rate2'] = 0;
        $fees_and_limits[GatewayType::ACSS]['fee_tax_name3'] = '';
        $fees_and_limits[GatewayType::ACSS]['fee_tax_rate3'] = 0;
        $fees_and_limits[GatewayType::ACSS]['adjust_fee_percent'] = false;
        $fees_and_limits[GatewayType::ACSS]['fee_cap'] = 0;
        $fees_and_limits[GatewayType::ACSS]['is_enabled'] = true;

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = self::ROTESSA_GATEWAY_KEY;
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(json_encode(['apiKey' => 'rotessa-test-key', 'testMode' => true]));
        $cg->fees_and_limits = $fees_and_limits;
        $cg->save();

        $settings = $this->client->settings;
        $settings->currency_id = '9'; // CAD
        $settings->company_gateway_ids = (string) $this->encodePrimaryKey($cg->id); // restrict to Rotessa only
        $this->client->settings = $settings;
        $this->client->country_id = 124; // Canada
        $this->client->save();
    }

    public function testRotessaOnlyClientStillResolvesAddPaymentMethodGateway(): void
    {
        $this->client = $this->client->fresh();

        // Rotessa is intentionally excluded from the generic bank-transfer resolver...
        $this->assertNull($this->client->getBankTransferGateway());

        // ...but the add-payment-method path and the dedicated ACSS resolver must find it.
        $this->assertNotNull($this->client->getBankTransferGateway(true));
        $this->assertNotNull($this->client->getACSSGateway());

        // The portal button wrapper relies on this union being truthy.
        $button_visible = $this->client->getCreditCardGateway()
            || $this->client->getBankTransferGateway()
            || $this->client->getBACSGateway()
            || $this->client->getACSSGateway();

        $this->assertTrue((bool) $button_visible);
    }

    public function testRotessaOnlyClientRendersAddPaymentMethodButton(): void
    {
        // Reload the contact so its client relation reflects the CAD/Canada/Rotessa
        // configuration applied in setUp (the in-memory factory relation is stale).
        $this->actingAs(ClientContact::find($this->contact->id), 'contact');

        Livewire::test(PaymentMethodsTable::class, [
            'client_id' => $this->client->id,
            'db' => $this->company->db,
        ])
            ->assertSee('add-payment-method')
            ->assertSee('add-bacs-link'); // the ACSS link reuses the add-bacs-link data-cy attribute
    }
}

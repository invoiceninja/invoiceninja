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

namespace Tests\Unit;

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Services\Invoice\AutoBillInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use stdClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Services\Invoice\AutoBillInvoice
 */
class AutoBillInvoiceTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const STRIPE_GATEWAY_KEY = 'd14dd26a37cecc30fdd65700bfb55b23';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testAutoBillFunctionality()
    {
        $this->assertEquals($this->client->balance, 10);
        $this->assertEquals($this->client->paid_to_date, 0);
        $this->assertEquals($this->client->credit_balance, 10);

        $this->invoice->service()->markSent()->autoBill();

        $this->assertNotNull($this->invoice->payments());
        $this->assertEquals(0, $this->invoice->payments()->sum('payments.amount'));

        $this->assertEquals(10, $this->invoice->payments()->get()->sum('pivot.amount'));

        $this->assertEquals($this->client->fresh()->balance, 0);
        $this->assertEquals($this->client->fresh()->paid_to_date, 10);
        $this->assertEquals($this->client->fresh()->credit_balance, 0);
    }

    public function testInactiveStripeAchTokenIsExcludedWithoutFeesAndLimits(): void
    {
        $token = $this->stripeAchToken('inactive');

        $this->assertFalse($this->autoBillService()->getGateway(10));
        $this->assertSame(0, (int) $token->fresh()->is_deleted);
    }

    public function testInactiveStripeAchTokenIsExcludedWithFeesAndLimits(): void
    {
        $this->stripeAchToken('inactive', true);

        $this->assertFalse($this->autoBillService()->getGateway(10));
    }

    public function testAuthorizedStripeAchTokenRemainsEligibleForAutoBilling(): void
    {
        $token = $this->stripeAchToken('authorized');

        $this->assertTrue($token->is($this->autoBillService()->getGateway(10)));
    }

    private function autoBillService(): AutoBillInvoice
    {
        return new AutoBillInvoice($this->invoice, $this->company->db);
    }

    private function stripeAchToken(string $state, bool $withFeesAndLimits = false): ClientGatewayToken
    {
        $feesAndLimits = new stdClass();

        if ($withFeesAndLimits) {
            $feesAndLimits->{GatewayType::BANK_TRANSFER} = (object) [
                'min_limit' => -1,
                'max_limit' => -1,
            ];
        }

        $companyGateway = new CompanyGateway();
        $companyGateway->company_id = $this->company->id;
        $companyGateway->user_id = $this->user->id;
        $companyGateway->gateway_key = self::STRIPE_GATEWAY_KEY;
        $companyGateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_auto_bill',
            'publishableKey' => 'pk_test_auto_bill',
        ]));
        $companyGateway->fees_and_limits = $feesAndLimits;
        $companyGateway->save();

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $companyGateway->id;
        $token->gateway_type_id = GatewayType::BANK_TRANSFER;
        $token->token = 'pm_auto_bill_test';
        $token->gateway_customer_reference = 'cus_auto_bill_test';
        $token->meta = (object) [
            'state' => $state,
            'last4' => '6789',
        ];
        $token->save();

        return $token;
    }
}

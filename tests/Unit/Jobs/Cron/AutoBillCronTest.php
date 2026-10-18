<?php

namespace Tests\Unit\Jobs\Cron;

use App\Jobs\Cron\AutoBillCron;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

class AutoBillCronTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testAutoBillCronQueryIncludesYmdAndYmdHisDueDates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-04 10:32:00'));

        $this->attachGatewayToken();

        $date_only_invoice = $this->autoBillableInvoice('2026-08-18');
        $date_time_invoice = $this->autoBillableInvoice('2026-08-18 00:00:00');
        $due_today_invoice = $this->autoBillableInvoice('2026-09-04 00:00:00');
        $future_invoice = $this->autoBillableInvoice('2026-09-05 00:00:00');

        $selected_ids = AutoBillCron::autoBillableInvoicesQuery()
            ->whereIn('id', [
                $date_only_invoice->id,
                $date_time_invoice->id,
                $due_today_invoice->id,
                $future_invoice->id,
            ])
            ->pluck('id')
            ->all();

        $this->assertContains($date_only_invoice->id, $selected_ids);
        $this->assertContains($date_time_invoice->id, $selected_ids);
        $this->assertContains($due_today_invoice->id, $selected_ids);
        $this->assertNotContains($future_invoice->id, $selected_ids);
    }

    private function autoBillableInvoice(string $due_date): Invoice
    {
        return Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'amount' => 100,
            'due_date' => $due_date,
            'auto_bill_enabled' => true,
            'auto_bill_tries' => 0,
            'is_deleted' => false,
            'discount' => 0,
            'is_amount_discount' => false,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]);
    }

    private function attachGatewayToken(): void
    {
        $company_gateway = new CompanyGateway();
        $company_gateway->company_id = $this->company->id;
        $company_gateway->user_id = $this->user->id;
        $company_gateway->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $company_gateway->config = encrypt(json_encode([
            'apiKey' => 'sk_test_auto_bill_cron',
            'publishableKey' => 'pk_test_auto_bill_cron',
        ]));
        $company_gateway->fees_and_limits = new \stdClass();
        $company_gateway->save();

        $token = new ClientGatewayToken();
        $token->company_id = $this->company->id;
        $token->client_id = $this->client->id;
        $token->company_gateway_id = $company_gateway->id;
        $token->gateway_type_id = GatewayType::CREDIT_CARD;
        $token->token = 'pm_auto_bill_cron';
        $token->gateway_customer_reference = 'cus_auto_bill_cron';
        $token->meta = (object) [
            'last4' => '4242',
        ];
        $token->save();
    }
}

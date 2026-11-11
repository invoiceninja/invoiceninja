<?php
namespace Tests\Feature\Scheduler;

use Tests\TestCase;
use App\Models\Invoice;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SeedProbe extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    private function probe(string $label, array $overrides): void
    {
        $invoice = Invoice::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'partial' => 0, 'partial_due_date' => null,
            'amount' => 300.00, 'balance' => 300.00,
            'status_id' => Invoice::STATUS_SENT,
        ], $overrides));

        // Mirror the UI's "number of payments" payload: frequency + remaining_cycles, empty schedule.
        $payload = [
            'template' => 'payment_schedule',
            'next_run' => now()->format('Y-m-d'),
            'remaining_cycles' => 3,
            'frequency_id' => 5, // monthly
            'parameters' => [
                'invoice_id' => $invoice->hashed_id,
                'auto_bill' => false,
                'schedule' => [],
            ],
        ];

        $r = $this->withHeaders(['X-API-SECRET'=>config('ninja.api_secret'),'X-API-TOKEN'=>$this->token])
            ->postJson('/api/v1/invoices/'.$invoice->hashed_id.'/payment_schedule?show_schedule=true', $payload);

        $invoice = $invoice->fresh();
        file_put_contents('/tmp/seed.log', $label.': http='.$r->status()
            .' partial='.var_export($invoice->partial, true)
            .' partial_due='.var_export((string)$invoice->partial_due_date, true)
            .' amount='.$invoice->amount.' balance='.$invoice->balance.PHP_EOL, FILE_APPEND);
    }

    public function testProbe()
    {
        @unlink('/tmp/seed.log');
        $this->probe('SENT amount=300 balance=300', []);
        $this->probe('DRAFT amount=300 balance=0', ['status_id' => Invoice::STATUS_DRAFT, 'balance' => 0]);
        $this->assertTrue(true);
    }
}

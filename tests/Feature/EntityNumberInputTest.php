<?php

namespace Tests\Feature;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class EntityNumberInputTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        // Purchase orders use company settings; the others use client settings.
        foreach ([$this->company, $this->client] as $owner) {
            $settings = $owner->settings;
            $settings->counter_number_applied = 'when_saved';
            $owner->settings = $settings;
            $owner->save();
        }
    }

    public static function numberInputs(): array
    {
        $cases = [];
        foreach (['invoices', 'quotes', 'credits', 'payments', 'purchase_orders', 'recurring_invoices'] as $endpoint) {
            foreach ([
                'omitted' => [],
                'null' => ['number' => null],
                'empty' => ['number' => ''],
                'whitespace' => ['number' => '   '],
                'integer' => ['number' => 987654321],
                'integer zero' => ['number' => 0],
                'string zero' => ['number' => '0'],
                'numeric string' => ['number' => '987654321'],
                'custom string' => ['number' => 'CUSTOM-987654321'],
            ] as $label => $input) {
                $expected = isset($input['number']) && trim((string) $input['number']) !== ''
                    ? (string) $input['number']
                    : null;

                $cases[$endpoint.' / '.$label] = [$endpoint, $input, $expected];
            }
        }

        return $cases;
    }

    #[DataProvider('numberInputs')]
    public function testCreateApiPreservesSuppliedNumbersOrGeneratesMissingNumbers(
        string $endpoint,
        array $number_input,
        ?string $expected_number,
    ): void {
        $payload = ['date' => now()->format('Y-m-d')];
        if ($endpoint === 'purchase_orders') {
            $payload['vendor_id'] = $this->vendor->hashed_id;
        } else {
            $payload['client_id'] = $this->client->hashed_id;
        }
        if ($endpoint === 'payments') {
            $payload['amount'] = 10;
        }
        if ($endpoint === 'recurring_invoices') {
            $payload['frequency_id'] = 5;
            $payload['next_send_date'] = now()->addMonth()->format('Y-m-d');
        }

        // Keep HTTP middleware and validation enabled: these tests establish
        // which inputs really reach the application, not just isolated guards.
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/'.$endpoint, array_merge($payload, $number_input));

        $response->assertStatus(200);
        $model_class = match ($endpoint) {
            'invoices' => Invoice::class,
            'quotes' => Quote::class,
            'credits' => Credit::class,
            'payments' => Payment::class,
            'purchase_orders' => PurchaseOrder::class,
            'recurring_invoices' => RecurringInvoice::class,
        };
        $model = $model_class::findOrFail($this->decodePrimaryKey($response->json('data.id')));
        $number = $model->number;
        if ($expected_number !== null) {
            $this->assertSame($expected_number, $number, 'Check the stored number independently of API serialization.');
        } else {
            $this->assertIsString($number);
            $this->assertNotSame('', trim($number));
            $this->assertNotSame('0', $number);
        }

        $model->service()->applyNumber()->save();
        $this->assertSame($number, $model->fresh()->number, 'Applying numbering again must preserve the saved number.');
        $this->assertSame($number, $response->json('data.number'), 'The API must expose the number that was actually saved.');
    }
}

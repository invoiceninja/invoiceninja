<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;

class InvoicePeriodTest extends TestCase
{
    use MockAccountData;

    protected UpdateInvoiceRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

    }

    

    public function testEInvoicePeriodValidationPasses()
    {
        $data = $this->invoice->toArray();
        $data['client_id'] = $this->client->hashed_id;
        $data['e_invoice'] = [
            'Invoice' => [
             'InvoicePeriod' => [
                [
                    'StartDate' => '2025-01-01',
                    'EndDate' => '2025-01-01',
                    ]    
             ]
            ]
        ];
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/'.$this->invoice->hashed_id, $data);

        $response->assertStatus(200);

        $arr = $response->json();

    }

    public function testERecurringInvoicePeriodValidationPasses()
    {
    
        $data = $this->recurring_invoice->toArray();
        
        $data['client_id'] = $this->client->hashed_id;
        $data['e_invoice'] = [
            'Invoice' => [
             'InvoicePeriod' => [
                [
                    'StartDate' => '2025-01-01',
                    'EndDate' => '2025-01-01',
                    'Description' => 'first day of this month|last day of this month'
                ]    
             ]
            ]
        ];
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/recurring_invoices/'.$this->recurring_invoice->hashed_id, $data);

        $arr = $response->json();
        
        $response->assertStatus(200);

        $this->assertEquals($arr['data']['e_invoice']['Invoice']['InvoicePeriod'][0]['Description'], 'first day of this month|last day of this month');

        $this->recurring_invoice = $this->recurring_invoice->fresh();

        $invoice = \App\Factory\RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice, $this->recurring_invoice->client);

        $this->assertEquals($invoice->e_invoice->Invoice->InvoicePeriod[0]->StartDate->date, now()->setTimezone($this->recurring_invoice->client->timezone()->name)->startOfMonth()->startOfDay()->format('Y-m-d H:i:s.u'));
        $this->assertEquals($invoice->e_invoice->Invoice->InvoicePeriod[0]->EndDate->date, now()->setTimezone($this->recurring_invoice->client->timezone()->name)->endOfMonth()->startOfDay()->format('Y-m-d H:i:s.u'));

    }

    public function testEInvoicePeriodValidationFails()
    {

        $data = $this->invoice->toArray();
        $data['e_invoice'] = [
            'Invoice' => [
                'InvoicePeriod' => [
                    'notarealvar' => '2025-01-01',
                    'worseVar' => '2025-01-01',
                    'Description' => 'Mustafa'
                ]
            ]
        ];
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/invoices/'.$this->invoice->hashed_id, $data);

        $arr = $response->json();

        nlog($arr);
        $response->assertStatus(422);


    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Factory\CompanyTokenFactory;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @test
 */
class MultiPaymentDeleteTest extends TestCase
{
    use DatabaseTransactions, MakesHash;

    private $faker;

    public function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

    }

    public function testComplexRefundDeleteScenario()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
                    'account_id' => $account->id,
                ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '11',
        ]);

        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $token = new CompanyToken;
        $token->user_id = $user->id;
        $token->company_id = $company->id;
        $token->account_id = $account->id;
        $token->name = 'test token';
        $token->token = 'okeytokey';
        $token->is_system = true;
        $token->save();

        $client = Client::factory()->create([
            'user_id' => $user->id, 
            'company_id' => $company->id,
        ]);  

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'number' => (string)$this->faker->randomNumber(6),
        ]);     

        $invoice = InvoiceFactory::create($company->id,$user->id);
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_DRAFT;

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 325;
        $item->type_id = 1;

        $line_items[] = $item;

        $invoice->line_items = $line_items;

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(0, $client->balance);
        $this->assertEquals(0, $invoice->balance);

        $invoice = $invoice->service()->markSent()->save();

        $invoice->fresh();
        $invoice->client->fresh();

        $this->assertEquals(325, $invoice->balance);
        $this->assertEquals(325, $invoice->client->balance);

        $data = [
            'amount' => 163.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                    [
                        'invoice_id' => $this->encodePrimaryKey($invoice->id),
                        'amount' => 163,
                    ],
                ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token->token,
        ])->post('/api/v1/payments/', $data);

        $arr = $response->json();
        $payment_id = $arr['data']['id'];
        $payment_1 = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals(162, $invoice->fresh()->balance);
        $this->assertEquals(162, $invoice->client->fresh()->balance);


        $data = [
            'amount' => 162.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                    [
                        'invoice_id' => $this->encodePrimaryKey($invoice->id),
                        'amount' => 162,
                    ],
                ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token->token,
        ])->post('/api/v1/payments/', $data);

        $arr = $response->json();
        $payment_id = $arr['data']['id'];
        $payment_2 = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals(0, $invoice->fresh()->balance);
        $this->assertEquals(0, $invoice->client->fresh()->balance);


        //refund payment 2 by 63 dollars

        $data = [
            'id' => $this->encodePrimaryKey($payment_2->id),
            'amount' => 63,
            'date' => '2021/12/12',
            'invoices' => [
                [
                'invoice_id' => $invoice->hashed_id,
                'amount' => 63,
                ],
            ],
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token->token,
        ])->post('/api/v1/payments/refund', $data);

        $this->assertEquals(63, $invoice->fresh()->balance);
        $this->assertEquals(63, $invoice->client->fresh()->balance);               
        
        
        //delete payment 2
        //
        $data = [
            'ids' => [$this->encodePrimaryKey($payment_2->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token->token,
        ])->post('/api/v1/payments/bulk?action=delete', $data);

        $this->assertEquals(162, $invoice->fresh()->balance);
        $this->assertEquals(162, $invoice->client->fresh()->balance);   
    }
}

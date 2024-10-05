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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;


class BankTransactionRuleApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    /*
    $rules = [
        'name' => 'bail|required|string',
        'rules' => 'bail|array',
        'auto_convert' => 'bail|sometimes|bool',
        'matches_on_all' => 'bail|sometimes|bool',
        'applies_to' => 'bail|sometimes|bool',
    ];

    if(isset($this->category_id))
        $rules['category_id'] = 'bail|sometimes|exists:expense_categories,id,'.auth()->user()->company()->id.',is_deleted,0';

    if(isset($this->vendor_id))
        $rules['vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

    if(isset($this->client_id))
        $rules['client_id'] = 'bail|sometimes|exists:clients,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';
    */
    public function testBankRuleCategoryIdValidation()
    {
        $data = [
           'name' => 'The First Rule',
           'rules' => [
            [
                "operator" => "contains",
                "search_key" => "description",
                "value" => "mobile"
            ],
           ],
           'assigned_user_id' => null,
           'auto_convert' => false,
           'matches_on_all' => true,
           'applies_to' => 'DEBIT',
           'category_id' => $this->expense_category->hashed_id,
           'vendor_id' => $this->vendor->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transaction_rules/', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals('DEBIT', $arr['data']['applies_to']);


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/bank_transaction_rules/'. $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals('DEBIT', $arr['data']['applies_to']);
    }

    public function testBankRulePost()
    {
        $data = [
           'name' => 'The First Rule',
           'rules' => [],
           'auto_convert' => false,
           'matches_on_all' => false,
           'applies_to' => 'CREDIT',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transaction_rules/', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals('The First Rule', $arr['data']['name']);
    }

    public function testBankRulePut()
    {
        $data = [
           'name' => 'The First Rule',
           'rules' => [],
           'auto_convert' => false,
           'matches_on_all' => false,
           'applies_to' => 'CREDIT',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/bank_transaction_rules/', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals('The First Rule', $arr['data']['name']);

        $data = [
           'name' => 'A New Name For The First Rule',
           'rules' => [],
           'auto_convert' => false,
           'matches_on_all' => false,
           'applies_to' => 'CREDIT',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/bank_transaction_rules/'. $arr['data']['id'], $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals('A New Name For The First Rule', $arr['data']['name']);
    }

    public function testBankTransactionRuleGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/bank_transaction_rules/'.$this->encodePrimaryKey($this->bank_transaction_rule->id));

        $response->assertStatus(200);
    }

    public function testBankTransactionRuleArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction_rule->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testBankTransactionRuleRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction_rule->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testBankTransactionRuleDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->bank_transaction_rule->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/bank_transaction_rules/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}

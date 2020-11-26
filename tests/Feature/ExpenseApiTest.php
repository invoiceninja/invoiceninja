<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Feature;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\ExpenseController
 */
class ExpenseApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testExpensePost()
    {
        $data = [
            'public_notes' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testExpensePut()
    {
        $data = [
            'public_notes' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id), $data);

        $response->assertStatus(200);
    }

    public function testExpenseGet()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $response->assertStatus(200);
    }

    public function testExpenseNotArchived()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testExpenseArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/expenses/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testExpenseRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/expenses/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testExpenseDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/expenses/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}

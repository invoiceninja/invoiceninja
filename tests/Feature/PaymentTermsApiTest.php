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

use App\DataMapper\DefaultSettings;
use App\Factory\PaymentTermFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\PaymentTerm;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 * @covers App\Http\Controllers\PaymentTermController
 */
class PaymentTermsApiTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testPaymentTermsGet()
    {
        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->get('/api/v1/payment_terms');

        $response->assertStatus(200);
    }

    public function testPostPaymentTerm()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payment_terms', ['num_days' => 50]);

        $response->assertStatus(200);

        $data = $response->json();

        $this->hashed_id = $data['data']['id'];
    }

    public function testPutPaymentTerms()
    {
        $payment_term = PaymentTermFactory::create($this->company->id, $this->user->id);
        $payment_term->num_days = 500;
        $payment_term->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/payment_terms/'.$this->encodePrimaryKey($payment_term->id), ['num_days' => 5000]);

        $response->assertStatus(200);
    }

    public function testDeletePaymentTerm()
    {
        $payment_term = PaymentTermFactory::create($this->company->id, $this->user->id);
        $payment_term->num_days = 500;
        $payment_term->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/payment_terms/'.$this->encodePrimaryKey($payment_term->id));

        $response->assertStatus(200);

        $payment_term = PaymentTerm::find($payment_term->id);

        $this->assertNull($payment_term);
    }
}

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

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * 
 */
class CheckDataTest extends TestCase
{
    protected $account;
    protected $user;
    protected $company;
    protected $cu;
    protected $token;
    protected $client;
    protected $faker;
    /**
     * Important consideration with Base64
     * encoding checks.
     *
     * No method can guarantee against false positives.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    private function buildData()
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
            'address1' => '1234',
            'balance' => 100,
            'paid_to_date' => 50,
        ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'first_name' => 'john',
                'last_name' => 'doe',
                'email' => 'john@doe.com'
            ]);

    }

    public function testDbQueriesRaw5()
    {
        $this->buildData();

        $i = Invoice::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        Invoice::where('status_id', 2)->cursor()->each(function ($i) {

            $i->service()->markPaid()->save();

        });

        Payment::with('paymentables')->cursor()->each(function ($payment) {
            $this->assertNotNull($payment->paymentables()->where('paymentable_type', \App\Models\Credit::class)->get()
            ->sum(\DB::raw('amount')->getValue(\DB::connection()->getQueryGrammar())));
        });

        Payment::with('paymentables')->cursor()->each(function ($payment) {
            $this->assertNotNull($payment->paymentables()->where('paymentable_type', \App\Models\Credit::class)->get()
            ->sum('amount'));
        });

        $amount = Paymentable::first()->payment->paymentables()->where('paymentable_type', 'invnoices')->get()->sum('amount');

        $this->assertNotNull($amount);

    }

    public function testDbQueriesRaw4()
    {
        $this->buildData();

        ClientContact::factory()->count(10)->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $clients_refactor = \DB::table('clients')
                    ->leftJoin('client_contacts', function ($join) {
                        $join->on('client_contacts.client_id', '=', 'clients.id');
                    })
                    ->get(['clients.id', \DB::raw('count(client_contacts.id) as contact_count')]);

        // $this->assertNotNull($clients);
        $this->assertNotNull($clients_refactor);

    }

    public function testDbQueriesRaw3()
    {
        $this->buildData();

        User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $user_hash = 'a';

        $user_count = User::where('account_id', $this->company->account->id)
            ->where(
                \DB::raw('CONCAT_WS(" ", first_name, last_name)'),
                'like',
                '%'.$user_hash.'%'
            )
            ->get();

        $user_count_refactor = User::whereRaw("account_id = ? AND CONCAT_WS(' ', first_name, last_name) like ?", [$this->company->account_id, '%'.$user_hash.'%'])
            ->get();


        $this->assertEquals($user_count_refactor->count(), $user_count->count());
    }

    public function testDbRawQueries1()
    {
        $this->buildData();

        $results = \DB::select(\DB::raw("
            SELECT count(clients.id) as count
            FROM clients
        ")->getValue(\DB::connection()->getQueryGrammar()));


        $refactored = \DB::select("
            SELECT count(clients.id) as count
            FROM clients
        ");

        $this->assertEquals($refactored[0]->count, $results[0]->count);

    }

    public function testDbRawQueries2()
    {
        $this->buildData();

        Payment::factory()->count(5)->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::factory()->count(5)->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::where('status_id', 2)->cursor()->each(function ($i) {

            $i->service()->markPaid()->save();

        });

        $results = \DB::select(\DB::raw("
                SELECT 
                SUM(payments.amount) as amount
                FROM payments
                LEFT JOIN paymentables
                ON
                payments.id = paymentables.payment_id
                WHERE paymentable_type = ?
                AND paymentables.deleted_at is NULL
                AND paymentables.amount > 0
                AND payments.is_deleted = 0
                AND payments.client_id = ?;
                ")->getValue(\DB::connection()->getQueryGrammar()), ['invoices', $this->client->id]);

        $refactored = \DB::select("
                SELECT 
                SUM(payments.amount) as amount
                FROM payments
                LEFT JOIN paymentables
                ON
                payments.id = paymentables.payment_id
                WHERE paymentable_type = ?
                AND paymentables.deleted_at is NULL
                AND paymentables.amount > 0
                AND payments.is_deleted = 0
                AND payments.client_id = ?;
                ", ['invoices', $this->client->id]);

        $this->assertEquals($refactored[0]->amount, $results[0]->amount);

    }

}

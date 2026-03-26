<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\ClientPortal;

use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    public function testClientCanViewOwnPayment(): void
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $settings = $client->settings;
        $settings->language_id = '1';
        $client->settings = $settings;
        $client->save();

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($client->contacts()->first(), 'contact');

        $response = $this->get(route('client.payments.show', ['payment' => $payment->hashed_id]));

        $response->assertStatus(200);

        $account->delete();
    }

    public function testClientCannotViewAnotherClientsPayment(): void
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $company = Company::factory()->create(['account_id' => $account->id]);
        $company->settings->language_id = '1';
        $company->save();

        // Client A - the one who will be authenticated
        $clientA = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $settings = $clientA->settings;
        $settings->language_id = '1';
        $clientA->settings = $settings;
        $clientA->save();

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $clientA->id,
            'company_id' => $company->id,
        ]);

        // Client B - owns the payment
        $clientB = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $clientB->id,
        ]);

        // Act as Client A trying to view Client B's payment
        $this->actingAs($clientA->contacts()->first(), 'contact');

        $response = $this->get(route('client.payments.show', ['payment' => $payment->hashed_id]));

        $response->assertStatus(403);

        $account->delete();
    }
}

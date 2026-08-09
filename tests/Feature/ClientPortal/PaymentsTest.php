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
use App\Models\Invoice;
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

    public function testCompletedVpsPaymentShowsReturnToServiceButton(): void
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

        $contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $vpsId = random_int(1, 100000);
        $returnUrl = "https://control.filefor.net/admin/vps/vm/{$vpsId}";
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);
        $lineItems = $invoice->line_items;
        $lineItems[0]->product_key = "vps-vm-{$vpsId}";
        $invoice->line_items = $lineItems;
        $invoice->saveQuietly();

        $unrelatedInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Payment::STATUS_COMPLETED,
        ]);
        $payment->invoices()->attach($unrelatedInvoice->id, [
            'amount' => 0,
            'refunded' => 0,
        ]);
        $payment->invoices()->attach($invoice->id, [
            'amount' => $payment->amount,
            'refunded' => 0,
        ]);

        $this->actingAs($contact, 'contact');

        $response = $this->get(route('client.payments.show', ['payment' => $payment->hashed_id]));

        $response
            ->assertOk()
            ->assertViewHas('return_url', $returnUrl)
            ->assertSeeText('Return to control panel')
            ->assertSee('href="' . $returnUrl . '"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);

        $explicitReturnUrl = 'https://control.filefor.net/admin/vps/vm/999999';
        $unrelatedInvoice->backup->redirect = $explicitReturnUrl;
        $unrelatedInvoice->saveQuietly();

        $this->get(route('client.payments.show', ['payment' => $payment->hashed_id]))
            ->assertOk()
            ->assertViewHas('return_url', $explicitReturnUrl)
            ->assertSee('href="' . $explicitReturnUrl . '"', false);

        $unrelatedInvoice->backup->redirect = 'file:///tmp/not-allowed';
        $unrelatedInvoice->saveQuietly();

        $this->get(route('client.payments.show', ['payment' => $payment->hashed_id]))
            ->assertOk()
            ->assertViewHas('return_url', $returnUrl)
            ->assertSee('href="' . $returnUrl . '"', false);

        $pendingPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Payment::STATUS_PENDING,
        ]);
        $pendingPayment->invoices()->attach($invoice->id, [
            'amount' => $pendingPayment->amount,
            'refunded' => 0,
        ]);

        $this->get(route('client.payments.show', ['payment' => $pendingPayment->hashed_id]))
            ->assertOk()
            ->assertViewHas('return_url', $returnUrl)
            ->assertDontSeeText('Return to control panel');

        $unrelatedPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Payment::STATUS_COMPLETED,
        ]);
        $unrelatedPayment->invoices()->attach($unrelatedInvoice->id, [
            'amount' => $unrelatedPayment->amount,
            'refunded' => 0,
        ]);

        $this->get(route('client.payments.show', ['payment' => $unrelatedPayment->hashed_id]))
            ->assertOk()
            ->assertViewHas('return_url', null)
            ->assertDontSeeText('Return to control panel');

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

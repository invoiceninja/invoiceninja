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

use App\Http\ViewComposers\PortalComposer;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RecurringInvoiceCancellationTest extends TestCase
{
    use DatabaseTransactions;
    use MakesHash;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    /**
     * Create an account, company, user, client, contact, and recurring invoice.
     */
    private function createClientWithRecurringInvoice(): array
    {
        $account = Account::factory()->create();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $contact = ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $recurringInvoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        return compact('account', 'user', 'company', 'client', 'contact', 'recurringInvoice');
    }

    public function testOwnerContactCanRequestCancellation(): void
    {
        $data = $this->createClientWithRecurringInvoice();

        $response = $this->actingAs($data['contact'], 'contact')
            ->get(route('client.recurring_invoices.request_cancellation', [
                'recurring_invoice' => $data['recurringInvoice']->hashed_id,
            ]));

        // Should not be 403 — the owner contact is authorized
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function testDifferentClientContactCannotRequestCancellation(): void
    {
        $data = $this->createClientWithRecurringInvoice();

        // Create a second client under the same company with its own contact
        $otherClient = Client::factory()->create([
            'company_id' => $data['company']->id,
            'user_id' => $data['user']->id,
        ]);

        $otherContact = ClientContact::factory()->create([
            'user_id' => $data['user']->id,
            'client_id' => $otherClient->id,
            'company_id' => $data['company']->id,
        ]);

        // Attempt to cancel a recurring invoice that belongs to a different client
        $response = $this->actingAs($otherContact, 'contact')
            ->get(route('client.recurring_invoices.request_cancellation', [
                'recurring_invoice' => $data['recurringInvoice']->hashed_id,
            ]));

        $response->assertStatus(403);
    }

    public function testContactFromDifferentCompanyCannotRequestCancellation(): void
    {
        $data = $this->createClientWithRecurringInvoice();

        // Create a completely separate company/client/contact
        $otherAccount = Account::factory()->create();

        $otherUser = User::factory()->create([
            'account_id' => $otherAccount->id,
            'email' => $this->faker->safeEmail(),
        ]);

        $otherCompany = Company::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $otherClient = Client::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
        ]);

        $otherContact = ClientContact::factory()->create([
            'user_id' => $otherUser->id,
            'client_id' => $otherClient->id,
            'company_id' => $otherCompany->id,
        ]);

        // Attempt to cancel a recurring invoice from a different company's client
        $response = $this->actingAs($otherContact, 'contact')
            ->get(route('client.recurring_invoices.request_cancellation', [
                'recurring_invoice' => $data['recurringInvoice']->hashed_id,
            ]));

        $response->assertStatus(403);
    }

    public function testCancellationDeniedWhenRecurringModuleDisabled(): void
    {
        $data = $this->createClientWithRecurringInvoice();

        // Disable the recurring invoices module by clearing its bit
        $data['company']->enabled_modules = $data['company']->enabled_modules & ~PortalComposer::MODULE_RECURRING_INVOICES;
        $data['company']->save();

        $response = $this->actingAs($data['contact'], 'contact')
            ->get(route('client.recurring_invoices.request_cancellation', [
                'recurring_invoice' => $data['recurringInvoice']->hashed_id,
            ]));

        $response->assertStatus(403);
    }
}

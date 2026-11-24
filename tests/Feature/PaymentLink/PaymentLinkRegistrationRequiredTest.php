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

namespace Tests\Feature\PaymentLink;

use App\DataMapper\CompanySettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentLinkRegistrationRequiredTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $account->id,
        ]);

        $settings = CompanySettings::defaults();
        $settings->language_id = '1';

        $this->company = Company::factory()->create([
            'account_id' => $account->id,
            'client_can_register' => true,
            'settings' => $settings,
        ]);
    }

    private function subscription(bool $registrationRequired): Subscription
    {
        return Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'registration_required' => $registrationRequired,
        ]);
    }

    public function test_anonymous_visitor_is_redirected_to_registration_when_required(): void
    {
        $subscription = $this->subscription(true);
        $url = route('client.subscription.purchase', ['subscription' => $subscription->hashed_id]);

        $response = $this->get($url);

        $response->assertRedirect(route('client.register', ['company_key' => $this->company->company_key]));
        $response->assertSessionHas('url.intended', $url);
    }

    public function test_anonymous_visitor_is_blocked_when_registration_is_required_but_disabled(): void
    {
        $this->company->client_can_register = false;
        $this->company->save();

        $subscription = $this->subscription(true);

        $response = $this->get(route('client.subscription.purchase', ['subscription' => $subscription->hashed_id]));

        $response->assertOk();
        $response->assertViewIs('portal.ninja2020.generic.subscription_blocked');
    }

    public function test_purchase_page_is_shown_when_registration_is_not_required(): void
    {
        $subscription = $this->subscription(false);

        $response = $this->get(route('client.subscription.purchase', ['subscription' => $subscription->hashed_id]));

        $response->assertOk();
        $response->assertViewIs('billing-portal.purchase');
    }

    public function test_logged_in_contact_sees_the_purchase_page(): void
    {
        $subscription = $this->subscription(true);

        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($contact, 'contact')
            ->get(route('client.subscription.purchase', ['subscription' => $subscription->hashed_id]));

        $response->assertOk();
        $response->assertViewIs('billing-portal.purchase');
    }
}

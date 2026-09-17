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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\ClientGatewayTokenFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\GatewayType;
use App\Models\User;
use App\Livewire\PaymentMethods\UpdateDefaultMethod;
use Livewire\Livewire;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PaymentMethodsTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private $faker;

    private $account;

    private $user;

    private $company;

    private $client;

    private $contact;

    private $otherClient;

    private $otherContact;

    private $companyGateway;

    private $cgt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $this->company = Company::factory()->create(['account_id' => $this->account->id]);
        $this->company->settings = CompanySettings::defaults();
        $this->company->save();

        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $settings = ClientSettings::defaults();
        $settings->language_id = '1';
        $this->client->settings = $settings;
        $this->client->save();

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->otherClient = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);
        $otherSettings = ClientSettings::defaults();
        $otherSettings->language_id = '1';
        $this->otherClient->settings = $otherSettings;
        $this->otherClient->save();

        $this->otherContact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->otherClient->id,
            'company_id' => $this->company->id,
        ]);

        $cg = new \App\Models\CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt('{}');
        $cg->fees_and_limits = [];
        $cg->save();
        $this->companyGateway = $cg;

        $cgt = ClientGatewayTokenFactory::create($this->company->id);
        $cgt->client_id = $this->client->id;
        $cgt->token = 'test_token';
        $cgt->gateway_customer_reference = 'cus_test';
        $cgt->company_gateway_id = $this->companyGateway->id;
        $cgt->gateway_type_id = GatewayType::CREDIT_CARD;
        $cgt->save();

        $this->cgt = $cgt;
    }

    public function testDefaultMethodOwnerCanSwitchAndRepeat(): void
    {
        $previous = $this->cgt->replicate();
        $previous->is_default = true;
        $previous->save();
        $this->cgt->is_default = false;
        $this->cgt->save();
        $this->actingAs($this->contact, 'contact');

        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $component->call('makeDefault')->assertDispatched('UpdateDefaultMethod::method-updated');
        $component->call('makeDefault')->assertSuccessful();

        $this->assertTrue((bool) $this->cgt->fresh()->is_default);
        $this->assertFalse((bool) $previous->fresh()->is_default);
    }

    public function testDefaultMethodTokenIdIsLocked(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('token_id', $this->cgt->id + 1);
    }

    public function testDefaultMethodDatabaseIsLocked(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $component->set('db', 'untrusted-connection');
    }

    public function testDefaultMethodRejectsChangedContact(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $before = $this->cgt->fresh()->is_default;
        $this->actingAs($this->otherContact, 'contact');

        $this->expectException(ModelNotFoundException::class);
        try {
            $component->call('makeDefault');
        } finally {
            $this->assertSame($before, $this->cgt->fresh()->is_default);
        }
    }

    public function testDefaultMethodRejectsExpiredAuthentication(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        auth()->guard('contact')->logout();

        $component->call('makeDefault')->assertForbidden();
    }

    public function testDefaultMethodRejectsForeignCompanyToken(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $otherCompany = Company::factory()->create(['account_id' => $this->account->id]);
        $this->cgt->company_id = $otherCompany->id;
        $this->cgt->save();

        $this->expectException(ModelNotFoundException::class);
        $component->call('makeDefault');
    }

    public function testDefaultMethodRejectsChangedDatabaseContext(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $trustedDatabase = config('database.default');
        $this->contact->company->db = 'another-tenant-connection';

        $component->call('makeDefault')->assertForbidden();
        $this->assertSame($trustedDatabase, config('database.default'));
    }

    public function testDefaultMethodRejectsArchivedToken(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $this->cgt->delete();

        $this->expectException(ModelNotFoundException::class);
        $component->call('makeDefault');
    }

    public function testDefaultMethodRejectsDeletedToken(): void
    {
        $this->actingAs($this->contact, 'contact');
        $component = Livewire::test(UpdateDefaultMethod::class, [
            'db' => $this->company->db,
            'token_id' => $this->cgt->id,
        ]);
        $this->cgt->is_deleted = true;
        $this->cgt->save();

        $this->expectException(ModelNotFoundException::class);
        $component->call('makeDefault');
    }

    public function testShowPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->get(route('client.payment_methods.show', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());

        $this->account->delete();
    }

    public function testShowPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $response = $this->get(route('client.payment_methods.show', $this->cgt->hashed_id));

        $response->assertStatus(403);


        $this->account->delete();
    }

    public function testDestroyPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->withoutExceptionHandling()
            ->delete(route('client.payment_methods.destroy', $this->cgt->hashed_id));

        $this->account->delete();
    }

    public function testDestroyPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->delete(route('client.payment_methods.destroy', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());

        $this->account->delete();
    }

    public function testVerifyPaymentMethodOtherClientForbidden(): void
    {
        $this->actingAs($this->otherContact, 'contact');

        $response = $this->get(route('client.payment_methods.verification', $this->cgt->hashed_id));

        $response->assertStatus(403);

        $this->account->delete();
    }

    public function testVerifyPaymentMethodOwnerCanAccess(): void
    {
        $this->actingAs($this->contact, 'contact');

        $response = $this->get(route('client.payment_methods.verification', $this->cgt->hashed_id));

        // Owner should not get 403 - authorization passes
        $this->assertNotEquals(403, $response->getStatusCode());

        $this->account->delete();
    }

}

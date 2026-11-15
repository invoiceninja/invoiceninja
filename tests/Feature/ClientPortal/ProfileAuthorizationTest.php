<?php

namespace Tests\Feature\ClientPortal;

use App\Http\Requests\ClientPortal\UpdateContactRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Client $client;

    private ClientContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['ninja.db.multi_db_enabled' => false]);

        $account = Account::factory()->create();
        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'email' => uniqid('profile-staff') . '@gmail.com',
        ]);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $this->client = Client::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
        ]);
        $this->contact = ClientContact::factory()->create([
            'company_id' => $company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'email' => uniqid('profile-contact') . '@gmail.com',
        ]);
    }

    #[DataProvider('authenticationStates')]
    public function testContactCanUpdateOwnProfileKeepingEmail(bool $with_staff): void
    {
        $this->authenticateContact($with_staff);

        $this->putJson(route('client.profile.update', ['client_contact' => $this->contact->hashed_id]), [
            'first_name' => 'Updated',
            'last_name' => 'Contact',
            'email' => $this->contact->email,
            'password' => 'updated-password',
            'password_confirmation' => 'updated-password',
        ])->assertRedirect();

        $this->contact->refresh();
        $this->assertSame('Updated', $this->contact->first_name);
        $this->assertSame('Contact', $this->contact->last_name);
        $this->assertTrue(Hash::check('updated-password', $this->contact->password));
    }

    #[DataProvider('authenticationStates')]
    public function testContactCanUpdateOwnClient(bool $with_staff): void
    {
        $this->authenticateContact($with_staff);

        $this->putJson(route('client.profile.edit_client', ['client_contact' => $this->contact->hashed_id]), [
            'name' => 'Updated client',
            'address1' => 'Updated address',
        ])->assertRedirect();

        $this->client->refresh();
        $this->assertSame('Updated client', $this->client->name);
        $this->assertSame('Updated address', $this->client->address1);
    }

    #[DataProvider('authenticationStates')]
    public function testRejectsEmailBelongingToAnotherContact(bool $with_staff): void
    {
        $other_contact = ClientContact::factory()->create([
            'company_id' => $this->contact->company_id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'email' => uniqid('other-contact') . '@gmail.com',
        ]);
        $original_email = $this->contact->email;
        $this->authenticateContact($with_staff);

        $this->putJson(route('client.profile.update', ['client_contact' => $this->contact->hashed_id]), [
            'first_name' => 'Updated',
            'last_name' => 'Contact',
            'email' => $other_contact->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame($original_email, $this->contact->fresh()->email);
    }

    public function testEmailValidationIgnoresContactRatherThanStaffIdentity(): void
    {
        $other_contact = ClientContact::factory()->create([
            'company_id' => $this->contact->company_id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'email' => uniqid('other-contact') . '@gmail.com',
        ]);
        $this->actingAs($this->contact, 'contact');
        $this->actingAs((new User())->forceFill(['id' => $other_contact->id]), 'user');

        $request = new UpdateContactRequest();
        $request->setUserResolver(static fn ($guard = null) => auth()->guard($guard)->user());
        $data = ['first_name' => 'Updated', 'last_name' => 'Contact', 'email' => $this->contact->email];

        $this->assertTrue(Validator::make($data, $request->rules())->passes());

        $data['email'] = $other_contact->email;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('email'));
    }

    #[DataProvider('profileRoutes')]
    public function testRejectsAnotherCompanyContactWithBothGuards(string $route_name): void
    {
        $other_account = Account::factory()->create();
        $other_user = User::factory()->create([
            'account_id' => $other_account->id,
            'email' => uniqid('other-staff') . '@gmail.com',
        ]);
        $other_company = Company::factory()->create(['account_id' => $other_account->id]);
        $other_client = Client::factory()->create([
            'company_id' => $other_company->id,
            'user_id' => $other_user->id,
        ]);
        $other_contact = ClientContact::factory()->create([
            'company_id' => $other_company->id,
            'client_id' => $other_client->id,
            'user_id' => $other_user->id,
        ]);
        $this->authenticateContact(true);

        $this->putJson(route($route_name, ['client_contact' => $other_contact->hashed_id]), [
            'name' => 'Unauthorized client',
            'first_name' => 'Unauthorized contact',
            'last_name' => 'Contact',
            'email' => $this->user->email,
            'password' => 'unauthorized-password',
            'password_confirmation' => 'unauthorized-password',
        ])->assertUnauthorized()->assertJsonPath('message', 'This action is unauthorized.');

        $this->assertSame($other_contact->first_name, $other_contact->fresh()->first_name);
        $this->assertSame($other_contact->email, $other_contact->fresh()->email);
        $this->assertSame($other_contact->password, $other_contact->fresh()->password);
        $this->assertSame($other_client->name, $other_client->fresh()->name);
    }

    #[DataProvider('profileRoutes')]
    public function testRejectsStaffWithoutAuthenticatedContact(string $route_name): void
    {
        $this->actingAs($this->user, 'user');

        $this->putJson(route($route_name, ['client_contact' => $this->contact->hashed_id]), [])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthenticated.');
    }

    public static function authenticationStates(): array
    {
        return [
            'contact only' => [false],
            'contact and staff' => [true],
        ];
    }

    public static function profileRoutes(): array
    {
        return [
            'contact profile' => ['client.profile.update'],
            'client profile' => ['client.profile.edit_client'],
        ];
    }

    private function authenticateContact(bool $with_staff): void
    {
        $this->actingAs($this->contact, 'contact');

        if ($with_staff) {
            $this->actingAs($this->user, 'user');
        }
    }
}

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

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Account;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Faker\Factory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactPasswordResetTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private $faker;

    private Account $account;

    private Company $company;

    private User $user;

    private Client $client;

    private ClientContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            ThrottleRequests::class,
        ]);
        Notification::fake();

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $this->company->settings->language_id = '1';
        $this->company->save();

        $this->client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'is_deleted' => false,
        ]);

        $this->contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('original-password'),
            'token' => Str::random(10),
        ]);
    }

    /**
     * Helper to send a password reset request via the forgot-password endpoint.
     */
    private function requestPasswordReset(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('client.password.email'), [
            'email' => $email,
        ]);
    }

    /**
     * Helper to complete a password reset via the reset endpoint.
     */
    private function completePasswordReset(string $email, string $token, string $password): \Illuminate\Testing\TestResponse
    {
        return $this->withSession([
            'company_key' => $this->company->company_key,
        ])->post(route('client.password.update'), [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /**
     * Verify the password reset request endpoint accepts valid emails.
     */
    public function testPasswordResetRequestRedirectsOnSuccess(): void
    {
        $response = $this->requestPasswordReset($this->contact->email);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    /**
     * After requesting a password reset, the contact's token column is
     * overwritten with a new 60-char token.
     */
    public function testPasswordResetRequestSetsTokenOnContact(): void
    {
        $originalToken = $this->contact->token;

        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();

        $this->assertNotEquals($originalToken, $this->contact->token);
        $this->assertEquals(60, strlen($this->contact->token));
    }

    /**
     * Verify the reset token allows the password to be changed.
     */
    public function testPasswordResetWithTokenSucceeds(): void
    {
        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $response = $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'new-secure-password'
        );

        $response->assertRedirect();

        $this->contact->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $this->contact->password));
    }

    /**
     * After a password reset completes, the token column should be cleared
     * so it cannot be reused as an API credential.
     *
     * EXPECTED FIX: This test will FAIL until the bug is fixed.
     * The token column is currently NOT cleared after password reset.
     */
    public function testTokenIsClearedAfterPasswordReset(): void
    {
        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'new-secure-password'
        );

        $this->contact->refresh();
        $this->assertNull(
            $this->contact->token,
            'The API token column should be cleared after password reset to prevent reuse as an API credential.'
        );
    }

    /**
     * A reset token that has already been used should not work again.
     *
     * EXPECTED FIX: This test will FAIL until the bug is fixed.
     */
    public function testUsedResetTokenCannotBeReusedForSecondReset(): void
    {
        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'first-new-password'
        );

        $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'second-new-password'
        );

        $this->contact->refresh();
        $this->assertTrue(
            Hash::check('first-new-password', $this->contact->password),
            'Password should remain as the first reset value; second reset with reused token must fail.'
        );
    }

    /**
     * Password reset should not succeed with an invalid token.
     */
    public function testPasswordResetFailsWithInvalidToken(): void
    {
        $this->completePasswordReset(
            $this->contact->email,
            'completely-invalid-token-value',
            'new-password-123'
        );

        $this->contact->refresh();
        $this->assertTrue(Hash::check('original-password', $this->contact->password));
    }

    /**
     * Password reset for a nonexistent email should not change anything.
     */
    public function testPasswordResetRequestForNonexistentEmailDoesNotChangeAnything(): void
    {
        $originalToken = $this->contact->token;

        $this->requestPasswordReset('nonexistent-user@example.com');

        $this->contact->refresh();
        $this->assertEquals($originalToken, $this->contact->token);
    }

    /**
     * On multi-company instances, requesting a password reset should only
     * update the token for contacts in the same company, not all contacts
     * sharing the same email across companies.
     *
     * EXPECTED FIX: This test will FAIL until the bug is fixed.
     * Currently updates tokens for ALL contacts with matching email.
     */
    public function testPasswordResetDoesNotAffectOtherCompanyContacts(): void
    {
        $company2 = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client2 = Client::factory()->create([
            'company_id' => $company2->id,
            'user_id' => $this->user->id,
            'is_deleted' => false,
        ]);

        $originalToken2 = Str::random(10);

        $contact2 = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client2->id,
            'company_id' => $company2->id,
            'email' => $this->contact->email,
            'token' => $originalToken2,
        ]);

        $this->requestPasswordReset($this->contact->email);

        $contact2->refresh();
        $this->assertEquals(
            $originalToken2,
            $contact2->token,
            'Password reset should not overwrite tokens for contacts in other companies sharing the same email.'
        );
    }

    /**
     * Verify that the reset endpoint hashes the new password.
     */
    public function testResetPasswordIsHashed(): void
    {
        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'my-new-password'
        );

        $this->contact->refresh();

        $this->assertNotEquals('my-new-password', $this->contact->password);
        $this->assertTrue(Hash::check('my-new-password', $this->contact->password));
    }

    /**
     * Verify that password confirmation mismatch is rejected.
     */
    public function testPasswordResetRequiresConfirmation(): void
    {
        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $response = $this->withSession([
                'company_key' => $this->company->company_key,
            ])->post(route('client.password.update'), [
                'email' => $this->contact->email,
                'token' => $resetToken,
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        // Password should remain unchanged
        $this->contact->refresh();
        $this->assertTrue(Hash::check('original-password', $this->contact->password));
    }

    /**
     * All contacts with the same email in the same company should have
     * their password updated when one resets.
     */
    public function testPasswordResetUpdatesAllContactsWithSameEmail(): void
    {
        $contact2 = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'email' => $this->contact->email,
            'password' => Hash::make('old-password-2'),
        ]);

        $this->requestPasswordReset($this->contact->email);

        $this->contact->refresh();
        $resetToken = $this->contact->token;

        $this->completePasswordReset(
            $this->contact->email,
            $resetToken,
            'shared-new-password'
        );

        $contact2->refresh();
        $this->assertTrue(
            Hash::check('shared-new-password', $contact2->password),
            'All contacts with the same email should have their password updated.'
        );
    }

    public function tearDown(): void
    {
        $this->account->delete();

        parent::tearDown();
    }
}

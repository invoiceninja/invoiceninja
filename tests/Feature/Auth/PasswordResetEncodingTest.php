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

namespace Tests\Feature\Auth;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\AppSetup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Regression coverage for the admin/account password reset link decoding.
 *
 * The reset link carries the token in the path segment and the email in the
 * query string. SPA / email link rewriters can single- or double-encode it.
 * Decoding now happens once, at the GET boundary (showResetForm), using
 * rawurldecode so '+' (plus-addressed emails) is preserved.
 */
class PasswordResetEncodingTest extends TestCase
{
    use DatabaseTransactions;
    use AppSetup;

    private Account $account;

    private Company $company;

    private User $user;

    private string $email = 'jane+ninja@example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            VerifyCsrfToken::class,
            ThrottleRequests::class,
        ]);

        $this->account = Account::factory()->create();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->email,
            'password' => Hash::make('original-password'),
        ]);

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $this->company->settings->language_id = '1';
        $this->company->save();
    }

    private function token(): string
    {
        return Password::broker()->createToken($this->user);
    }

    /**
     * The reset form is rendered with the token and a single-encoded
     * plus-addressed email decoded back to their canonical values.
     */
    public function testFormDecodesSingleEncodedPlusEmail(): void
    {
        $token = $this->token();

        $response = $this->get('/password/reset/'.$token.'?email='.rawurlencode($this->email));

        $response->assertOk();
        $response->assertSee('name="token" value="'.$token.'"', false);
        $response->assertSee('value="'.$this->email.'"', false);
        $response->assertDontSee('jane ninja@example.com', false);
    }

    /**
     * A double-encoded email in the link is still decoded back to the
     * canonical address.
     */
    public function testFormDecodesDoubleEncodedPlusEmail(): void
    {
        $token = $this->token();
        $doubleEncoded = rawurlencode(rawurlencode($this->email));

        $response = $this->get('/password/reset/'.$token.'?email='.$doubleEncoded);

        $response->assertOk();
        $response->assertSee('value="'.$this->email.'"', false);
    }

    /**
     * A valid token + plus-addressed email posted from the form changes
     * the password (the '+' must survive, unlike the old urldecode path).
     */
    public function testResetSucceedsForPlusAddressedEmail(): void
    {
        $token = $this->token();

        $response = $this->post(route('password.update'), [
            'email' => $this->email,
            'token' => $token,
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertTrue(Hash::check('new-secure-password-123', $this->user->password));
    }

    /**
     * An invalid token must not change the password.
     */
    public function testResetFailsWithInvalidToken(): void
    {
        $this->post(route('password.update'), [
            'email' => $this->email,
            'token' => 'completely-invalid-token-value',
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ]);

        $this->user->refresh();
        $this->assertTrue(Hash::check('original-password', $this->user->password));
    }

    public function tearDown(): void
    {
        $this->account->delete();

        parent::tearDown();
    }
}

<?php

namespace Tests\Feature;

use App\DataMapper\Billing\BillingContext;
use App\Http\Controllers\ClientPortal\NinjaPlanController;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Stripe\Exception\ApiConnectionException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Tests\TestCase;

class NinjaPlanTrialAuthorizationTest extends TestCase
{
    public function test_exact_one_dollar_credit_card_authorization_is_valid(): void
    {
        $this->assertTrue($this->validateAuthorization(
            $this->paymentIntent(),
            $this->paymentMethod('credit')
        ));
    }

    public function test_wrong_amount_customer_or_status_is_invalid(): void
    {
        $this->assertFalse($this->validateAuthorization(
            $this->paymentIntent(['amount' => 99]),
            $this->paymentMethod('credit')
        ));
        $this->assertFalse($this->validateAuthorization(
            $this->paymentIntent(['customer' => 'cus_other']),
            $this->paymentMethod('credit')
        ));
        $this->assertFalse($this->validateAuthorization(
            $this->paymentIntent(['status' => 'succeeded']),
            $this->paymentMethod('credit')
        ));
    }

    public function test_every_stripe_authorization_invariant_fails_closed(): void
    {
        $paymentIntentCases = [
            ['capture_method' => 'automatic'],
            ['amount_capturable' => 99],
            ['currency' => 'aud'],
            ['livemode' => true],
            ['review' => ['id' => 'prv_review']],
            ['metadata' => ['client_id' => '999', 'contact_id' => '20']],
            ['metadata' => ['client_id' => '10', 'contact_id' => '999']],
        ];

        foreach ($paymentIntentCases as $overrides) {
            $this->assertFalse($this->validateAuthorization(
                $this->paymentIntent($overrides),
                $this->paymentMethod('credit')
            ));
        }

        foreach ([
            ['type' => 'us_bank_account'],
            ['customer' => 'cus_other'],
        ] as $overrides) {
            $this->assertFalse($this->validateAuthorization(
                $this->paymentIntent(),
                $this->paymentMethod('credit', $overrides)
            ));
        }
    }

    public function test_only_a_session_confirmed_release_accepts_a_canceled_intent(): void
    {
        $paymentIntent = $this->paymentIntent([
            'status' => PaymentIntent::STATUS_CANCELED,
            'amount_capturable' => 0,
        ]);

        $this->assertFalse($this->validateAuthorization(
            $paymentIntent,
            $this->paymentMethod('credit')
        ));
        $this->assertTrue($this->validateAuthorization(
            $paymentIntent,
            $this->paymentMethod('credit'),
            true
        ));
    }

    public function test_debit_prepaid_and_unknown_cards_are_invalid(): void
    {
        foreach (['debit', 'prepaid', 'unknown'] as $funding) {
            $this->assertFalse($this->validateAuthorization(
                $this->paymentIntent(),
                $this->paymentMethod($funding)
            ));
        }
    }

    public function test_endpoint_cancels_a_debit_authorization_and_returns_dedicated_json(): void
    {
        $contact = (new ClientContact())->forceFill([
            'id' => 20,
            'client_id' => 10,
        ]);
        $contact->setRelation('client', (new Client())->forceFill([
            'id' => 10,
            'company_id' => 1,
        ]));
        $company = new Company();
        $company->setRelation('account', (new Account())->forceFill([
            'report_errors' => false,
        ]));
        $contact->setRelation('company', $company);
        Auth::guard('contact')->setUser($contact);
        $paymentIntent = new FakeCancelableTrialPaymentIntent(
            $this->paymentIntent([
                'payment_method' => $this->paymentMethod('debit'),
            ])
        );
        $controller = new FakeStripeTrialController($paymentIntent);
        $request = Request::create('/client/ninja/trial_confirmation', 'POST', [
            'payment_intent_id' => 'pi_trial',
        ]);
        $session = new Store(
            'trial-test',
            new ArraySessionHandler(120)
        );
        $session->start();
        $session->put('ninja_plan_trial_authorizations.pi_trial', [
            'payment_intent_id' => 'pi_trial',
            'customer_id' => 'cus_trial',
            'client_id' => 10,
            'contact_id' => 20,
            'released' => false,
        ]);
        $request->setLaravelSession($session);

        $response = $controller->trial_confirmation($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1, $paymentIntent->cancelCalls);
        $this->assertSame(
            ctrans('texts.trial_credit_card_required'),
            $response->getData(true)['message']
        );
    }

    public function test_ambiguous_cancel_is_retrieved_and_recorded_as_released(): void
    {
        $contact = (new ClientContact())->forceFill([
            'id' => 20,
            'client_id' => 10,
        ]);
        $contact->setRelation('client', (new Client())->forceFill([
            'id' => 10,
            'company_id' => 1,
        ]));
        $company = new Company();
        $company->setRelation('account', (new Account())->forceFill([
            'report_errors' => false,
        ]));
        $contact->setRelation('company', $company);
        Auth::guard('contact')->setUser($contact);
        $original = new FakeCancelableTrialPaymentIntent(
            $this->paymentIntent([
                'payment_method' => $this->paymentMethod('credit'),
            ]),
            throwOnCancel: true
        );
        $recovered = new FakeCancelableTrialPaymentIntent(
            $this->paymentIntent([
                'status' => PaymentIntent::STATUS_CANCELED,
                'amount_capturable' => 0,
                'payment_method' => $this->paymentMethod('credit'),
            ])
        );
        $controller = new FakeStripeTrialController($original, $recovered);
        $request = Request::create('/client/ninja/trial_confirmation', 'POST', [
            'payment_intent_id' => 'pi_trial',
        ]);
        $session = new Store('trial-test', new ArraySessionHandler(120));
        $session->start();
        $session->put('ninja_plan_trial_authorizations.pi_trial', [
            'payment_intent_id' => 'pi_trial',
            'customer_id' => 'cus_trial',
            'client_id' => 10,
            'contact_id' => 20,
            'released' => false,
        ]);
        $request->setLaravelSession($session);

        try {
            $controller->trial_confirmation($request);
            $this->fail('Profile validation should stop this isolated endpoint test.');
        } catch (ValidationException) {
            $this->assertTrue(
                $session->get('ninja_plan_trial_authorizations.pi_trial.released')
            );
            $this->assertSame(1, $original->cancelCalls);
            $this->assertSame(2, $controller->retrieveCalls);
        }
    }

    public function test_trial_view_uses_payment_intent_and_inline_json_submission(): void
    {
        $view = file_get_contents(
            resource_path('views/portal/ninja2020/plan/trial.blade.php')
        );

        $this->assertStringContainsString('stripe.confirmCardPayment', $view);
        $this->assertStringContainsString("Accept: 'application/json'", $view);
        $this->assertStringContainsString(
            "contentType.includes('application/json')",
            $view
        );
        $this->assertStringContainsString(
            "payload.redirect_url.startsWith(window.location.origin)",
            $view
        );
        $this->assertStringNotContainsString('confirmCardSetup', $view);
        $this->assertStringNotContainsString('form.submit()', $view);
    }

    public function test_trial_provisioning_uses_configured_lock_and_exact_retry_markers(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/ClientPortal/NinjaPlanController.php')
        );

        $this->assertStringContainsString(
            'Cache::lock(',
            $controller
        );
        $this->assertStringNotContainsString("Cache::store('redis')", $controller);
        $this->assertStringContainsString(
            "ninja_plan_trial:{\$paymentIntentId}",
            $controller
        );
        $this->assertStringContainsString(
            "->where('private_notes', \$marker)",
            $controller
        );
        $this->assertStringContainsString(
            "->where('company_gateway_id', \$gateway->id)",
            $controller
        );
        $this->assertStringContainsString(
            'ninja_plan_trial_authorizations',
            $controller
        );
        $this->assertStringContainsString(
            'array_slice($authorizations, -10, preserve_keys: true)',
            $controller
        );

        $checkpointPosition = strpos(
            $controller,
            '$this->setTrialBillingContext($account, $client->id, $recurringInvoice->id);'
        );
        $activationPosition = strpos(
            $controller,
            '$recurringInvoice->service()->applyNumber()->start()->save();'
        );

        $this->assertIsInt($checkpointPosition);
        $this->assertIsInt($activationPosition);
        $this->assertLessThan($activationPosition, $checkpointPosition);
    }

    public function test_trial_billing_context_update_preserves_existing_state(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(
            client_id: 10,
            recurring_invoice_id: 20,
            pricing: [
                'plan_price' => 14,
                'docuninja_price' => 8,
            ],
            docuninja_pending_prune: true,
        );
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'setTrialBillingContext'
        );

        $method->invoke(new NinjaPlanController(), $account, 30, 40);

        $this->assertSame(30, $account->billing_context->client_id);
        $this->assertSame(40, $account->billing_context->recurring_invoice_id);
        $this->assertSame(
            ['plan_price' => 14, 'docuninja_price' => 8],
            $account->billing_context->pricing
        );
        $this->assertTrue($account->billing_context->docuninja_pending_prune);
    }

    public function test_trial_lock_key_is_account_scoped_and_does_not_expose_the_account_key(): void
    {
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'trialProvisioningLockKey'
        );
        $controller = new NinjaPlanController();
        $first = $method->invoke($controller, 'account-key-one');
        $second = $method->invoke($controller, 'account-key-two');

        $this->assertSame($first, $method->invoke($controller, 'account-key-one'));
        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('ninja-plan-trial:provision:', $first);
        $this->assertStringNotContainsString('account-key-one', $first);
    }

    public function test_trial_lock_contention_does_not_execute_the_provisioning_callback(): void
    {
        config(['cache.default' => 'array']);
        $controller = new NinjaPlanController();
        $keyMethod = new ReflectionMethod(
            NinjaPlanController::class,
            'trialProvisioningLockKey'
        );
        $lockMethod = new ReflectionMethod(
            NinjaPlanController::class,
            'withTrialProvisioningLock'
        );
        $lock = Cache::lock($keyMethod->invoke($controller, 'locked-account'), 10);
        $callbackExecuted = false;

        $this->assertTrue($lock->get());

        try {
            $lockMethod->invoke(
                $controller,
                'locked-account',
                function () use (&$callbackExecuted): array {
                    $callbackExecuted = true;

                    return [];
                },
                0
            );
            $this->fail('A contended trial lock was acquired.');
        } catch (LockTimeoutException) {
            $this->assertFalse($callbackExecuted);
        } finally {
            $lock->release();
        }
    }

    public function test_checkpoint_accepts_a_valid_invoice_from_an_earlier_payment_intent(): void
    {
        $client = (new Client())->forceFill([
            'id' => 10,
        ]);
        $subscription = (new Subscription())->forceFill([
            'id' => 6,
            'company_id' => 20,
        ]);
        $recurringInvoice = (new RecurringInvoice())->forceFill([
            'company_id' => 20,
            'client_id' => 10,
            'subscription_id' => 6,
            'private_notes' => 'ninja_plan_trial:pi_earlier',
            'is_deleted' => false,
            'status_id' => RecurringInvoice::STATUS_DRAFT,
        ]);
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'isValidTrialRecurringInvoice'
        );

        $this->assertTrue($method->invoke(
            new NinjaPlanController(),
            $recurringInvoice,
            $client,
            $subscription
        ));
    }

    public function test_checkpoint_fails_closed_for_invalid_ownership_marker_or_state(): void
    {
        $client = (new Client())->forceFill(['id' => 10]);
        $subscription = (new Subscription())->forceFill([
            'id' => 6,
            'company_id' => 20,
        ]);
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'isValidTrialRecurringInvoice'
        );
        $valid = [
            'company_id' => 20,
            'client_id' => 10,
            'subscription_id' => 6,
            'private_notes' => 'ninja_plan_trial:pi_checkpoint',
            'is_deleted' => false,
            'status_id' => RecurringInvoice::STATUS_DRAFT,
        ];

        foreach ([
            ['client_id' => 11],
            ['company_id' => 21],
            ['subscription_id' => 7],
            ['private_notes' => 'not-a-trial'],
            ['is_deleted' => true],
            ['status_id' => RecurringInvoice::STATUS_PAUSED],
        ] as $invalid) {
            $this->assertFalse($method->invoke(
                new NinjaPlanController(),
                (new RecurringInvoice())->forceFill(array_merge($valid, $invalid)),
                $client,
                $subscription
            ));
        }
    }

    public function test_success_redirect_preserves_contact_authentication_and_domain_middleware_is_not_duplicated(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/ClientPortal/NinjaPlanController.php')
        );
        $route = app('router')->getRoutes()->getByName('client.trial.confirmed');

        $this->assertStringNotContainsString(
            "Auth::guard('contact')->login(\$contact, true);",
            $controller
        );
        $this->assertNotNull($route);
        $this->assertSame(
            1,
            count(array_filter(
                $route->gatherMiddleware(),
                static fn(string $middleware): bool => $middleware === 'domain_db'
            ))
        );
        $this->assertContains('auth:contact', $route->gatherMiddleware());
        $this->assertContains('check_client_existence', $route->gatherMiddleware());
    }

    public function test_success_url_is_not_captured_by_ninja_contact_login_wildcard(): void
    {
        $route = app('router')->getRoutes()->match(
            \Illuminate\Http\Request::create(
                '/client/ninja/trial/confirmed',
                'GET'
            )
        );

        $this->assertSame('client.trial.confirmed', $route->getName());
        $this->assertSame(
            NinjaPlanController::class . '@trialConfirmed',
            $route->getActionName()
        );
    }

    public function test_account_that_previously_started_a_trial_is_never_eligible_again(): void
    {
        $eligibleAccount = (new Account())->forceFill([
            'plan' => null,
            'is_trial' => false,
            'trial_started' => null,
        ]);
        $previouslyTrialedAccount = (new Account())->forceFill([
            'plan' => null,
            'is_trial' => false,
            'trial_started' => now(),
        ]);
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'isTrialEligible'
        );

        $this->assertTrue($method->invoke(
            new NinjaPlanController(),
            $eligibleAccount
        ));
        $this->assertFalse($method->invoke(
            new NinjaPlanController(),
            $previouslyTrialedAccount
        ));
    }

    private function validateAuthorization(
        PaymentIntent $paymentIntent,
        PaymentMethod $paymentMethod,
        bool $released = false
    ): bool {
        $contact = new ClientContact();
        $contact->id = 20;
        $contact->client_id = 10;
        $method = new ReflectionMethod(
            NinjaPlanController::class,
            'isValidTrialAuthorization'
        );

        return $method->invoke(
            new NinjaPlanController(),
            $paymentIntent,
            $paymentMethod,
            [
                'customer_id' => 'cus_trial',
                'released' => $released,
            ],
            $contact
        );
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function paymentIntent(array $overrides = []): PaymentIntent
    {
        return PaymentIntent::constructFrom(array_merge([
            'id' => 'pi_trial',
            'status' => 'requires_capture',
            'capture_method' => 'manual',
            'amount' => 100,
            'amount_capturable' => 100,
            'currency' => 'usd',
            'customer' => 'cus_trial',
            'livemode' => false,
            'review' => null,
            'metadata' => [
                'client_id' => '10',
                'contact_id' => '20',
            ],
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function paymentMethod(string $funding, array $overrides = []): PaymentMethod
    {
        return PaymentMethod::constructFrom(array_merge([
            'id' => 'pm_trial',
            'type' => 'card',
            'customer' => 'cus_trial',
            'card' => [
                'funding' => $funding,
            ],
        ], $overrides));
    }
}

class FakeStripeTrialController extends NinjaPlanController
{
    /** @var list<PaymentIntent> */
    private array $paymentIntents;

    public int $retrieveCalls = 0;

    public function __construct(PaymentIntent ...$paymentIntents)
    {
        $this->paymentIntents = $paymentIntents;
    }

    protected function trialGateway(): CompanyGateway
    {
        return new FakeTrialCompanyGateway();
    }

    protected function retrieveTrialPaymentIntent(
        string $paymentIntentId,
        mixed $gatewayDriver
    ): PaymentIntent {
        $paymentIntent = $this->paymentIntents[
            min($this->retrieveCalls, count($this->paymentIntents) - 1)
        ];
        $this->retrieveCalls++;

        return $paymentIntent;
    }
}

class FakeTrialCompanyGateway extends CompanyGateway
{
    public function driver(?Client $client = null): FakeTrialStripeDriver
    {
        return new FakeTrialStripeDriver();
    }
}

class FakeTrialStripeDriver
{
    /** @var array<string, mixed> */
    public array $stripe_connect_auth = [];

    public function init(): self
    {
        return $this;
    }
}

class FakeCancelableTrialPaymentIntent extends PaymentIntent
{
    public int $cancelCalls = 0;

    public function __construct(
        PaymentIntent $paymentIntent,
        private readonly bool $throwOnCancel = false
    ) {
        parent::__construct($paymentIntent->id);
        $this->refreshFrom($paymentIntent->toArray(), null);
        $this->payment_method = $paymentIntent->payment_method;
    }

    public function cancel($params = null, $opts = null): PaymentIntent
    {
        $this->cancelCalls++;

        if ($this->throwOnCancel) {
            throw ApiConnectionException::factory('Simulated timeout.');
        }

        $this->status = PaymentIntent::STATUS_CANCELED;

        return $this;
    }
}

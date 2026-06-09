<?php

namespace Tests\Unit;

use App\DataMapper\Billing\BillingContext;
use App\Models\Account;
use InvalidArgumentException;
use Tests\TestCase;

class BillingContextCastTest extends TestCase
{
    public function testItReturnsNullForMissingBillingContext(): void
    {
        $account = new Account();
        $account->setRawAttributes(['billing_context' => null]);

        $this->assertNull($account->billing_context);
    }

    public function testItCastsJsonIntoBillingContext(): void
    {
        $account = new Account();
        $account->setRawAttributes([
            'billing_context' => json_encode([
                'version' => 1,
                'client_id' => 123,
                'recurring_invoice_id' => 456,
                'current_plan_key' => 'enterprise_plan_5',
                'term' => 'month',
                'num_users' => 5,
                'docuninja_users' => 2,
                'plan_started' => '2026-06-01',
                'plan_paid' => '2026-06-01',
                'plan_expires' => '2026-07-01',
                'last_quote_id' => 'quote_123',
                'pending_change' => ['path' => 'docuninja_downgrade'],
            ], JSON_THROW_ON_ERROR),
        ]);

        $context = $account->billing_context;

        $this->assertInstanceOf(BillingContext::class, $context);
        $this->assertSame(123, $context->client_id);
        $this->assertSame(456, $context->recurring_invoice_id);
        $this->assertSame('enterprise_plan_5', $context->current_plan_key);
        $this->assertSame('month', $context->term);
        $this->assertSame(5, $context->num_users);
        $this->assertSame(2, $context->docuninja_users);
        $this->assertSame('2026-06-01', $context->plan_started);
        $this->assertSame('2026-06-01', $context->plan_paid);
        $this->assertSame('2026-07-01', $context->plan_expires);
        $this->assertSame('quote_123', $context->last_quote_id);
        $this->assertSame(['path' => 'docuninja_downgrade'], $context->pending_change);
        $this->assertTrue($context->hasRecurringInvoice());
    }

    public function testItSerializesBillingContextForStorage(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(
            client_id: 123,
            recurring_invoice_id: 456,
            current_plan_key: 'pro',
            term: 'year',
            num_users: 1,
            docuninja_users: 0,
            plan_started: '2026-06-01',
            plan_paid: '2026-06-01',
            plan_expires: '2027-06-01',
        );

        $stored = json_decode($account->getAttributes()['billing_context'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $stored['version']);
        $this->assertSame(123, $stored['client_id']);
        $this->assertSame(456, $stored['recurring_invoice_id']);
        $this->assertSame('pro', $stored['current_plan_key']);
        $this->assertSame('year', $stored['term']);
        $this->assertSame(1, $stored['num_users']);
        $this->assertArrayNotHasKey('pending_change', $stored);
    }

    public function testItAcceptsAccountFieldAliasesAndStoresCanonicalKeys(): void
    {
        $account = new Account();
        $account->billing_context = [
            'client_id' => '123',
            'recurring_invoice_id' => '456',
            'plan' => 'enterprise_plan_10',
            'plan_term' => 'month',
            'docuninja_num_users' => 3,
            'quote_id' => 'quote_456',
        ];

        $this->assertSame('enterprise_plan_10', $account->billing_context->current_plan_key);
        $this->assertSame('month', $account->billing_context->term);
        $this->assertSame(3, $account->billing_context->docuninja_users);
        $this->assertSame('quote_456', $account->billing_context->last_quote_id);

        $stored = json_decode($account->getAttributes()['billing_context'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('enterprise_plan_10', $stored['current_plan_key']);
        $this->assertSame('month', $stored['term']);
        $this->assertSame(3, $stored['docuninja_users']);
        $this->assertSame('quote_456', $stored['last_quote_id']);
        $this->assertArrayNotHasKey('plan', $stored);
        $this->assertArrayNotHasKey('plan_term', $stored);
        $this->assertArrayNotHasKey('docuninja_num_users', $stored);
        $this->assertArrayNotHasKey('quote_id', $stored);
    }

    public function testItPreservesUnknownFutureKeys(): void
    {
        $context = BillingContext::fromArray([
            'client_id' => 123,
            'gateway_subscription_id' => 'sub_123',
            'extra' => [
                'billing_provider' => 'invoice_ninja',
            ],
        ]);

        $this->assertSame('sub_123', $context->extra['gateway_subscription_id']);
        $this->assertSame('invoice_ninja', $context->extra['billing_provider']);
        $this->assertSame('sub_123', $context->toArray()['gateway_subscription_id']);
        $this->assertSame('invoice_ninja', $context->toArray()['billing_provider']);
    }

    public function testItStoresNullForEmptyBillingContext(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext();

        $this->assertNull($account->getAttributes()['billing_context']);
    }

    public function testItRejectsInvalidJson(): void
    {
        $account = new Account();
        $account->setRawAttributes(['billing_context' => '{']);

        $this->expectException(InvalidArgumentException::class);

        $account->billing_context;
    }
}

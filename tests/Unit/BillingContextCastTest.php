<?php

namespace Tests\Unit;

use App\DataMapper\Billing\BillingContext;
use App\Models\Account;
use Tests\TestCase;

class BillingContextCastTest extends TestCase
{
    public function testItReturnsNullForMissingBillingContext(): void
    {
        $account = new Account();
        $account->setRawAttributes(['billing_context' => null]);

        $this->assertNull($account->billing_context);
    }

    public function testItReturnsNullForEmptyBillingContextPayload(): void
    {
        $account = new Account();
        $account->setRawAttributes([
            'billing_context' => json_encode([
                'version' => 1,
                'pricing' => [],
                'docuninja_pending_prune' => false,
            ], JSON_THROW_ON_ERROR),
        ]);

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
                'pricing' => [
                    'plan_price' => 14,
                    'docuninja_price' => 6,
                ],
                'docuninja_pending_prune' => true,
            ], JSON_THROW_ON_ERROR),
        ]);

        $context = $account->billing_context;

        $this->assertInstanceOf(BillingContext::class, $context);
        $this->assertSame(123, $context->client_id);
        $this->assertSame(456, $context->recurring_invoice_id);
        $this->assertSame(14.0, $context->pricing['plan_price']);
        $this->assertSame(6.0, $context->pricing['docuninja_price']);
        $this->assertTrue($context->docuninja_pending_prune);
    }

    public function testItSerializesBillingContextForStorage(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(
            client_id: 123,
            recurring_invoice_id: 456,
            pricing: [
                'plan_price' => 14,
                'docuninja_price' => 6,
            ],
            docuninja_pending_prune: true,
        );

        $stored = json_decode($account->getAttributes()['billing_context'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $stored['version']);
        $this->assertSame(123, $stored['client_id']);
        $this->assertSame(456, $stored['recurring_invoice_id']);
        $this->assertSame(14.0, (float) $stored['pricing']['plan_price']);
        $this->assertSame(6.0, (float) $stored['pricing']['docuninja_price']);
        $this->assertTrue($stored['docuninja_pending_prune']);
    }

    public function testItStoresOnlyBillingPointerAndPricingState(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(
            client_id: 123,
            recurring_invoice_id: 456,
            pricing: [
                'plan_price' => '14.129',
                'docuninja_price' => '6.126',
            ],
            docuninja_pending_prune: true,
        );

        $stored = json_decode($account->getAttributes()['billing_context'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(123, $stored['client_id']);
        $this->assertSame(456, $stored['recurring_invoice_id']);
        $this->assertSame(14.13, $stored['pricing']['plan_price']);
        $this->assertSame(6.13, $stored['pricing']['docuninja_price']);
        $this->assertTrue($stored['docuninja_pending_prune']);
    }

    public function testItIgnoresUnknownFieldsWhenHydrating(): void
    {
        $context = BillingContext::fromArray([
            'client_id' => 123,
            'recurring_invoice_id' => 456,
            'pricing' => [
                'plan_price' => '14.129',
                'docuninja_price' => '6.126',
            ],
            'docuninja_pending_prune' => true,
            'plan' => 'enterprise_plan_10',
            'plan_term' => 'month',
            'num_users' => 10,
            'docuninja_num_users' => 3,
            'plan_expires' => '2026-07-01',
            'quote_id' => 'quote_456',
            'unexpected_key' => 'ignored',
        ]);

        $this->assertSame(123, $context->client_id);
        $this->assertSame(456, $context->recurring_invoice_id);
        $this->assertSame(14.13, $context->pricing['plan_price']);
        $this->assertSame(6.13, $context->pricing['docuninja_price']);
        $this->assertTrue($context->docuninja_pending_prune);
    }

    public function testItOmitsEmptyPricingAndFalseDocuNinjaFlag(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(client_id: 123);

        $stored = json_decode($account->getAttributes()['billing_context'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(123, $stored['client_id']);
        $this->assertArrayNotHasKey('pricing', $stored);
        $this->assertArrayNotHasKey('docuninja_pending_prune', $stored);
    }

    public function testItStoresNullForEmptyBillingContext(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext();

        $this->assertNull($account->getAttributes()['billing_context']);
    }

    public function testItStoresNullWhenClientIdIsMissing(): void
    {
        $account = new Account();
        $account->billing_context = new BillingContext(
            recurring_invoice_id: 456,
            pricing: [
                'plan_price' => 14,
                'docuninja_price' => 6,
            ],
            docuninja_pending_prune: true,
        );

        $this->assertNull($account->getAttributes()['billing_context']);
    }

    public function testItReturnsNullForInvalidJson(): void
    {
        $account = new Account();
        $account->setRawAttributes(['billing_context' => '{']);

        $this->assertNull($account->billing_context);
    }
}

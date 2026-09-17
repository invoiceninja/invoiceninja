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

namespace Tests\Feature\Bank;

use App\Jobs\Bank\ProcessBankTransactionsYodlee;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Exercises the cross-integration deduplication predicate used when ingesting
 * Yodlee transactions, independent of the Yodlee API.
 */
class YodleeDeduplicationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private BankIntegration $current_integration;

    private BankIntegration $previous_integration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->previous_integration = $this->makeIntegration();
        $this->current_integration = $this->makeIntegration();
    }

    private function makeIntegration(): BankIntegration
    {
        return BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
            'user_id' => $this->user->id,
            'integration_type' => BankIntegration::INTEGRATION_TYPE_YODLEE,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedTransaction(BankIntegration $integration, array $overrides = []): BankTransaction
    {
        return BankTransaction::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'bank_integration_id' => $integration->id,
            'transaction_id' => 1000,
            'amount' => 50.00,
            'currency_id' => 1,
            'account_type' => 'checking',
            'category_type' => 'TRANSFER',
            'date' => '2026-06-01',
            'description' => 'STARBUCKS STORE 123',
            'participant' => '500.00',
            'participant_name' => null,
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function incoming(array $overrides = []): array
    {
        return array_merge([
            'transaction_id' => 2000,
            'amount' => 50.00,
            'currency_id' => 1,
            'account_type' => 'checking',
            'category_id' => null,
            'category_type' => 'TRANSFER',
            'date' => '2026-06-01',
            'bank_account_id' => 1,
            'description' => 'STARBUCKS STORE 123',
            'base_type' => 'DEBIT',
            'participant' => '500.00',
            'participant_name' => null,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $transaction
     */
    private function isDuplicate(array $transaction): bool
    {
        $job = new ProcessBankTransactionsYodlee('token', $this->current_integration);

        $method = new ReflectionMethod($job, 'isDuplicateTransaction');

        return $method->invoke($job, $transaction);
    }

    public function testNovelTransactionIsNotDuplicate(): void
    {
        $this->assertFalse($this->isDuplicate($this->incoming()));
    }

    public function testExactUpstreamIdIsDuplicate(): void
    {
        $this->seedTransaction($this->current_integration, ['transaction_id' => 7777]);

        $this->assertTrue($this->isDuplicate($this->incoming(['transaction_id' => 7777])));
    }

    public function testRelinkUnderNewIntegrationWithSameRunningBalanceIsDuplicate(): void
    {
        // The original transaction lives under the previous (now stale) integration,
        // with a freshly-issued upstream id on the incoming copy — only the
        // company-grain natural key can catch it.
        $this->seedTransaction($this->previous_integration, [
            'transaction_id' => 1000,
            'participant' => '500.00',
        ]);

        $this->assertTrue($this->isDuplicate($this->incoming([
            'transaction_id' => 2000,
            'participant' => '500.00',
        ])));
    }

    public function testTwoDistinctIdenticalTransactionsBothSurvive(): void
    {
        // Same amount, day, and description, but a different running balance proves
        // they are two real payments and not a re-imported duplicate.
        $this->seedTransaction($this->current_integration, [
            'transaction_id' => 1000,
            'participant' => '500.00',
        ]);

        $this->assertFalse($this->isDuplicate($this->incoming([
            'transaction_id' => 2000,
            'participant' => '450.00',
        ])));
    }

    public function testNullRunningBalanceOnLegacyRowStillDeduplicates(): void
    {
        // Legacy rows captured before running_balance existed must not start
        // producing duplicates on the next sync.
        $this->seedTransaction($this->previous_integration, [
            'transaction_id' => 1000,
            'participant' => null,
        ]);

        $this->assertTrue($this->isDuplicate($this->incoming([
            'transaction_id' => 2000,
            'participant' => '450.00',
        ])));
    }
}

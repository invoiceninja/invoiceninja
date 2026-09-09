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

use App\Helpers\Bank\EnableBanking\Transformer\AccountTransformer;
use App\Helpers\Bank\EnableBanking\Transformer\TransactionTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Offline unit/feature coverage for the EnableBanking transformers.
 *
 * These tests exercise the pure transformation logic only; they do not hit the
 * EnableBanking HTTP API, so no credentials are required.
 */
#[CoversClass(TransactionTransformer::class)]
#[CoversClass(AccountTransformer::class)]
class EnableBankingTransformerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function sampleTransaction(array $overrides = []): array
    {
        return array_merge([
            'entry_reference' => '5561990681',
            'transaction_amount' => ['currency' => 'EUR', 'amount' => '1.23'],
            'credit_debit_indicator' => 'CRDT',
            'status' => 'BOOK',
            'booking_date' => '2020-01-03',
            'note' => 'Invoice 1234',
            'exchange_rate' => null,
            'debtor' => ['name' => 'MyPreferredAisp'],
            'debtor_account' => ['iban' => 'FI0455231152453547'],
        ], $overrides);
    }

    public function testTransformOnlyKeepsBookedTransactions()
    {
        $transformer = new TransactionTransformer($this->company);

        $result = $transformer->transform([
            'transactions' => [
                $this->sampleTransaction(['entry_reference' => 'booked-1', 'status' => 'BOOK']),
                $this->sampleTransaction(['entry_reference' => 'pending-1', 'status' => 'PDNG']),
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertEquals('booked-1', $result[0]['enablebanking_transaction_id']);
    }

    public function testTransformThrowsOnMissingTransactionsKey()
    {
        $this->expectException(\Exception::class);

        (new TransactionTransformer($this->company))->transform([]);
    }

    public function testTransformTransactionMapsCoreFields()
    {
        $transformer = new TransactionTransformer($this->company);

        $data = $transformer->transformTransaction($this->sampleTransaction());

        $this->assertEquals('5561990681', $data['enablebanking_transaction_id']);
        $this->assertEquals(1.23, $data['amount']);
        $this->assertEquals('CREDIT', $data['base_type']);
        $this->assertEquals('2020-01-03', $data['date']);
        // debtor takes precedence over creditor for participant data
        $this->assertEquals('FI0455231152453547', $data['participant']);
        $this->assertEquals('MyPreferredAisp', $data['participant_name']);
        $this->assertEquals('Invoice 1234', $data['description']);
    }

    public function testTransformTransactionDebitIndicator()
    {
        $transformer = new TransactionTransformer($this->company);

        $data = $transformer->transformTransaction($this->sampleTransaction([
            'credit_debit_indicator' => 'DBIT',
            'transaction_amount' => ['currency' => 'EUR', 'amount' => '-50.00'],
        ]));

        $this->assertEquals('DEBIT', $data['base_type']);
        // amount is always stored as an absolute value
        $this->assertEquals(50.00, $data['amount']);
    }

    public function testTransformTransactionThrowsWithoutEntryReference()
    {
        $this->expectException(\Exception::class);

        $transaction = $this->sampleTransaction();
        unset($transaction['entry_reference']);

        (new TransactionTransformer($this->company))->transformTransaction($transaction);
    }

    public function testSelectBalancePrefersClosingBooked()
    {
        $balance = $this->invokeSelectBalance([
            ['balance_type' => 'ITBD', 'balance_amount' => ['amount' => '10.00', 'currency' => 'EUR']],
            ['balance_type' => 'CLBD', 'balance_amount' => ['amount' => '99.00', 'currency' => 'EUR']],
        ]);

        $this->assertEquals('99.00', $balance['balance_amount']['amount']);
    }

    public function testSelectBalanceFallsBackToFirst()
    {
        $balance = $this->invokeSelectBalance([
            ['balance_type' => 'ITBD', 'balance_amount' => ['amount' => '10.00', 'currency' => 'EUR']],
            ['balance_type' => 'XPCD', 'balance_amount' => ['amount' => '20.00', 'currency' => 'EUR']],
        ]);

        $this->assertEquals('10.00', $balance['balance_amount']['amount']);
    }

    public function testSelectBalanceHandlesEmptyList()
    {
        $this->assertEquals([], $this->invokeSelectBalance([]));
    }

    public function testMaskAccountNumber()
    {
        $transformer = new AccountTransformer();
        $method = new \ReflectionMethod($transformer, 'maskAccountNumber');
        $method->setAccessible(true);

        $this->assertEquals('**** 2453547', $method->invoke($transformer, 'FI0455231152453547'));
        $this->assertEquals('', $method->invoke($transformer, ''));
    }

    private function invokeSelectBalance(array $balances): array
    {
        $transformer = new AccountTransformer();
        $method = new \ReflectionMethod($transformer, 'selectBalance');
        $method->setAccessible(true);

        return $method->invoke($transformer, $balances);
    }
}

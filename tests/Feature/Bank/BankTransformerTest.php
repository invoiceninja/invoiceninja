<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Bank;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Import\Transformer\Bank\BankTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BankTransformerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private BankTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->transformer = new BankTransformer($this->company);
    }

    /**
     * When only the amount field is provided (no credit/debit columns, no base_type,
     * no category_type), the sign of the amount should determine the base_type:
     *   positive amount → CREDIT
     *   negative amount → DEBIT
     */
    public function testPositiveAmountOnlyReturnsCreditType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '100.50',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(100.50, $result['amount']);
        $this->assertEquals('CREDIT', $result['base_type']);
    }

    public function testNegativeAmountOnlyReturnsDebitType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '-100.50',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(100.50, $result['amount']);
        $this->assertEquals('DEBIT', $result['base_type']);
    }

    public function testZeroAmountReturnsDebitType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '0',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(0, $result['amount']);
        $this->assertEquals('DEBIT', $result['base_type']);
    }

    /**
     * Formatted amounts (currency symbols, thousand separators) should still
     * correctly determine credit/debit from the sign.
     */
    public function testFormattedPositiveAmountReturnsCreditType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '$1,234.56',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(1234.56, $result['amount']);
        $this->assertEquals('CREDIT', $result['base_type']);
    }

    public function testFormattedNegativeAmountReturnsDebitType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '-$1,234.56',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(1234.56, $result['amount']);
        $this->assertEquals('DEBIT', $result['base_type']);
    }

    public function testLargePositiveAmountReturnsCreditType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '10000.00',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(10000.00, $result['amount']);
        $this->assertEquals('CREDIT', $result['base_type']);
    }

    public function testSmallNegativeAmountReturnsDebitType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '-0.01',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(0.01, $result['amount']);
        $this->assertEquals('DEBIT', $result['base_type']);
    }

    /**
     * When explicit base_type is provided, it should take precedence over amount sign.
     */
    public function testExplicitBaseTypeOverridesAmountSign()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.amount' => '-500.00',
            'transaction.base_type' => 'CREDIT',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(500.00, $result['amount']);
        $this->assertEquals('CREDIT', $result['base_type']);
    }

    /**
     * When separate credit/debit columns are used instead of a single amount.
     */
    public function testCreditColumnReturnsCreditType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.payment_type_Credit' => '250.00',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(250.00, $result['amount']);
        $this->assertEquals('CREDIT', $result['base_type']);
    }

    public function testDebitColumnReturnsDebitType()
    {
        $transaction = [
            'transaction.bank_integration_id' => 1,
            'transaction.payment_type_Debit' => '75.00',
        ];

        $result = $this->transformer->transform($transaction);

        $this->assertEquals(75.00, $result['amount']);
        $this->assertEquals('DEBIT', $result['base_type']);
    }
}

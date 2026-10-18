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

use App\Helpers\Bank\Nordigen\Transformer\TransactionTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class NordigenTransactionTransformerTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private TransactionTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->transformer = new TransactionTransformer($this->company);
    }

    public function testCreditPrefersDebtorWhenBothPartiesArePresent(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'credit-1',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'Incoming payment',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '18.23',
            ],
            'debtorName' => 'Payer GmbH',
            'debtorAccount' => [
                'iban' => 'DE02120300000000202051',
            ],
            'creditorName' => 'BUDI SVOJ D.O.O.',
            'creditorAccount' => [
                'iban' => 'HR2723600001102578029',
            ],
        ]);

        $this->assertEquals('CREDIT', $result['base_type']);
        $this->assertEquals('Payer GmbH', $result['participant_name']);
        $this->assertEquals('DE02120300000000202051', $result['participant']);
    }

    public function testDebitPrefersCreditorWhenBothPartiesArePresent(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'L180262410010527',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'HR01 2203071265-260804-0',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '-18.23',
            ],
            'debtorName' => 'BUDI SVOJ D.O.O.',
            'debtorAccount' => [
                'iban' => 'HR2723600001102578029',
            ],
            'creditorName' => 'Payee d.o.o.',
            'creditorAccount' => [
                'iban' => 'HR1210010051863000160',
            ],
        ]);

        $this->assertEquals('DEBIT', $result['base_type']);
        $this->assertEquals(18.23, $result['amount']);
        $this->assertEquals('Payee d.o.o.', $result['participant_name']);
        $this->assertEquals('HR1210010051863000160', $result['participant']);
    }

    public function testDebitFallsBackToDebtorWhenCreditorIsMissing(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'debit-fallback',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'Card payment',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '-10.00',
            ],
            'debtorName' => 'Only Debtor',
            'debtorAccount' => [
                'iban' => 'HR2723600001102578029',
            ],
        ]);

        $this->assertEquals('DEBIT', $result['base_type']);
        $this->assertEquals('Only Debtor', $result['participant_name']);
        $this->assertEquals('HR2723600001102578029', $result['participant']);
    }

    public function testCreditFallsBackToCreditorWhenDebtorIsMissing(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'credit-fallback',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'Incoming payment',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '25.00',
            ],
            'creditorName' => 'Only Creditor',
            'creditorAccount' => [
                'iban' => 'DE02120300000000202051',
            ],
        ]);

        $this->assertEquals('CREDIT', $result['base_type']);
        $this->assertEquals('Only Creditor', $result['participant_name']);
        $this->assertEquals('DE02120300000000202051', $result['participant']);
    }

    public function testMissingCounterpartyFieldsLeaveParticipantNull(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'no-party',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'PAYMENT Alderaan Coffe',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '-15.00',
            ],
        ]);

        $this->assertEquals('DEBIT', $result['base_type']);
        $this->assertNull($result['participant']);
        $this->assertNull($result['participant_name']);
    }

    public function testAccountWithoutIbanIsIgnored(): void
    {
        $result = $this->transformer->transformTransaction([
            'transactionId' => 'no-iban',
            'bookingDate' => '2026-08-31',
            'remittanceInformationUnstructured' => 'Outgoing payment',
            'transactionAmount' => [
                'currency' => 'EUR',
                'amount' => '-18.23',
            ],
            'debtorName' => 'BUDI SVOJ D.O.O.',
            'debtorAccount' => [
                'iban' => 'HR2723600001102578029',
            ],
            'creditorName' => 'Payee d.o.o.',
            'creditorAccount' => [
                'bban' => '12345678',
            ],
        ]);

        $this->assertEquals('DEBIT', $result['base_type']);
        $this->assertEquals('Payee d.o.o.', $result['participant_name']);
        $this->assertEquals('HR2723600001102578029', $result['participant']);
    }
}

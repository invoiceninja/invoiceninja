<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\PaymentDrivers\Helcim;

use App\PaymentDrivers\Helcim\HelcimAchTransaction;
use PHPUnit\Framework\TestCase;

class HelcimAchTransactionTest extends TestCase
{
    public function testApprovedAuthorizationWithOpenedClearingIsPending(): void
    {
        $transaction = HelcimAchTransaction::from([
            'transaction' => [
                'transactionId' => 123,
                'orderId' => 456,
                'invoiceNumber' => 'IN-123',
                'statusAuth' => 1,
                'statusClearing' => 0,
                'amount' => 25.5,
                'currencyId' => 2,
            ],
        ]);

        $this->assertSame('123', $transaction->transactionId);
        $this->assertSame('456', $transaction->orderId);
        $this->assertSame('IN-123', $transaction->invoiceNumber);
        $this->assertSame('APPROVED', $transaction->authorizationStatus);
        $this->assertSame('OPENED', $transaction->clearingStatus);
        $this->assertSame('USD', $transaction->currency);
        $this->assertTrue($transaction->isPending());
        $this->assertFalse($transaction->isCompleted());
    }

    public function testOnlyClearedClearingStatusCompletesAnAchPayment(): void
    {
        $transaction = HelcimAchTransaction::from([
            'transactionId' => 124,
            'statusAuth' => 'APPROVED',
            'statusClearing' => 'CLEARED',
        ]);

        $this->assertTrue($transaction->isCompleted());
        $this->assertFalse($transaction->isPending());
    }

    public function testGenericApprovedAndSuccessResponsesAreNotClearingConfirmation(): void
    {
        $approved = HelcimAchTransaction::from(['transactionId' => 127, 'status' => 'APPROVED']);
        $success = HelcimAchTransaction::from(['transactionId' => 128, 'status' => 'SUCCESS']);

        $this->assertTrue($approved->isPending());
        $this->assertTrue($success->isPending());
        $this->assertFalse($approved->isCompleted());
        $this->assertFalse($success->isCompleted());
    }

    public function testReturnedAndRejectedClearingStatusesFail(): void
    {
        $returned = HelcimAchTransaction::from([
            'transactionId' => 125,
            'statusAuth' => 'APPROVED',
            'statusClearing' => 'RETURNED',
        ]);
        $rejected = HelcimAchTransaction::from([
            'transactionId' => 126,
            'statusAuth' => 1,
            'statusClearing' => 4,
        ]);

        $this->assertTrue($returned->isFailed());
        $this->assertTrue($rejected->isFailed());
        $this->assertFalse($returned->isCompleted());
    }
}

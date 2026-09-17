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

namespace Tests\Unit\EInvoice;

use App\Models\Credit;
use App\Services\EDocument\Standards\Validation\EntityLevelInterface;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel as PeppolEntityLevel;
use App\Services\EDocument\Standards\Validation\Verifactu\EntityLevel as VerifactuEntityLevel;
use PHPUnit\Framework\TestCase;

class CreditEntityValidationTest extends TestCase
{
    public function testVerifactuCheckCreditPassesWithoutTypeError(): void
    {
        $credit = $this->createMock(Credit::class);

        $result = (new VerifactuEntityLevel())->checkCredit($credit);

        $this->assertTrue($result['passes']);
        $this->assertSame([], $result['invoice']);
        $this->assertSame([], $result['credit']);
        $this->assertSame([], $result['client']);
        $this->assertSame([], $result['company']);
    }

    public function testPeppolAndVerifactuImplementCheckCredit(): void
    {
        $this->assertTrue(method_exists(PeppolEntityLevel::class, 'checkCredit'));
        $this->assertTrue(method_exists(VerifactuEntityLevel::class, 'checkCredit'));
        $this->assertTrue(is_a(PeppolEntityLevel::class, EntityLevelInterface::class, true));
        $this->assertTrue(is_a(VerifactuEntityLevel::class, EntityLevelInterface::class, true));
    }

    public function testPeppolCheckCreditRemapsInvoiceErrorsOntoCredit(): void
    {
        $credit = $this->createMock(Credit::class);

        $el = $this->getMockBuilder(PeppolEntityLevel::class)
            ->onlyMethods(['checkInvoice'])
            ->getMock();

        $el->method('checkInvoice')->willReturn([
            'passes' => false,
            'invoice' => ['Negative line price'],
            'client' => [],
            'company' => [],
        ]);

        $result = $el->checkCredit($credit);

        $this->assertArrayHasKey('credit', $result);
        $this->assertSame([], $result['invoice']);
        $this->assertSame(['Negative line price'], $result['credit']);
        $this->assertFalse($result['passes']);
        $this->assertSame([], $result['client']);
        $this->assertSame([], $result['company']);
    }
}

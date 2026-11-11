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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Tests\Integration\Einvoice\Storecove\Support\UblStorecoveTestHarness;

/**
 * Ground-truth probes: the only oracle is the PEPPOL schematron.
 *
 * No arithmetic is asserted here. Each test builds a document through the
 * production pipeline and asks Saxon whether the emitted UBL is valid.
 * Raw schematron messages are printed so the failure is self-describing.
 */
class SchematronProbeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use UblStorecoveTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->bootUblStorecoveHarness();
    }

    /** Control: the shape the refactor was built for. */
    public function testPositiveCreditWithDiscountAndOffsetRowIsValid(): void
    {
        $credit = $this->harnessCredit($this->harnessClient(), [
            $this->harnessLineItem('A', 9490, 1, 0),
            $this->harnessLineItem('B', 100, 2, 10),
            $this->harnessLineItem('C', 4590, -1, 10),
        ]);

        $this->assertSchematronClean($this->buildUbl($credit)['xml']);
    }

    /** A negative-total invoice is emitted as a CreditNote; give it a line discount. */
    public function testNegativeTotalInvoiceWithLineDiscountIsValid(): void
    {
        $invoice = $this->harnessInvoice($this->harnessClient(), [
            $this->harnessLineItem('N1', 100, -2, 10),
        ]);

        $ubl = $this->buildUbl($invoice);

        $this->assertTrue($ubl['is_credit_note'], 'negative invoice should emit a CreditNote');
        $this->assertSchematronClean($ubl['xml']);
    }

    /** Credit whose total is negative because every line is an offset row. */
    public function testNegativeTotalCreditWithLineDiscountIsValid(): void
    {
        $credit = $this->harnessCredit($this->harnessClient(), [
            $this->harnessLineItem('Offset', 4590, -1, 10),
        ]);

        $this->assertLessThan(0, (float) $credit->amount);
        $this->assertSchematronClean($this->buildUbl($credit)['xml']);
    }

    /** Fails the build with the raw schematron output when the UBL is invalid. */
    private function assertSchematronClean(string $xml): void
    {
        if (!$this->harnessHasSaxon) {
            $this->markTestSkipped('saxon not installed');
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->validate();

        $messages = [];

        foreach ($validator->getErrors() as $category => $errors) {
            foreach ((array) $errors as $msg) {
                $messages[] = "[{$category}] {$msg}";
            }
        }

        $this->assertSame([], $messages, "Schematron rejected the UBL:\n" . implode("\n", $messages));
    }
}

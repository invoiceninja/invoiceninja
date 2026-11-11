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

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use App\Helpers\Epc\EpcQrGenerator;
use App\Models\Company;
use App\Models\Invoice;
use Tests\TestCase;

class EpcQrGeneratorTest extends TestCase
{
    public function testInvoiceNumberUsesUnstructuredRemittance(): void
    {
        $settings = CompanySettings::defaults();
        $settings->name = 'Test Company';

        $company = new Company();
        $company->settings = $settings;

        $invoice = new Invoice();
        $invoice->number = 'INV-001';

        $payload = (new EpcQrGenerator($company, $invoice, 100))->encodeMessage();
        $lines = explode("\n", $payload);

        $this->assertCount(11, $lines);
        $this->assertSame('', $lines[8]); // Purpose
        $this->assertSame('', $lines[9]); // Structured remittance
        $this->assertSame('INV-001', $lines[10]); // Unstructured remittance
        $this->assertFalse(str_ends_with($payload, "\n"));
    }

    public function test_qr_code_wrapper_has_a_white_background(): void
    {
        $settings = CompanySettings::defaults();
        $settings->name = 'Test Company';

        $company = new Company();
        $company->settings = $settings;

        $invoice = new Invoice();
        $invoice->number = 'INV-001';

        $svg = html_entity_decode((new EpcQrGenerator($company, $invoice, 100))->getQrCode());

        $this->assertStringContainsString(
            "<rect x='0' y='0' width='100%' height='100%' fill='#ffffff' />",
            $svg
        );
        $this->assertStringNotContainsString("width='100%''", $svg);
    }
}

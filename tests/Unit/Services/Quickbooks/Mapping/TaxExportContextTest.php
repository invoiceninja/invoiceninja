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

namespace Tests\Unit\Services\Quickbooks\Mapping;

use App\DataMapper\QuickbooksSync;
use App\Services\Quickbooks\Mapping\TaxExportContext;
use Tests\TestCase;

class TaxExportContextTest extends TestCase
{
    public function test_from_quickbooks_sync_forces_us_tax_non_literals(): void
    {
        $context = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => '123',
            'default_exempt_code' => '456',
            'tax_rate_map' => [['id' => 'r1']],
            'composite_tax_code_map' => ['gst:5.0000' => '40'],
        ]));

        $this->assertTrue($context->isUs());
        $this->assertSame('TAX', $context->taxable_code);
        $this->assertSame('NON', $context->exempt_code);
        $this->assertTrue($context->includesTxnTaxDetail());
        $this->assertFalse($context->usesTaxExcludedCalculation());
        $this->assertSame([['id' => 'r1']], $context->tax_rate_map);
        $this->assertSame(['gst:5.0000' => '40'], $context->composite_tax_code_map);
    }

    public function test_from_quickbooks_sync_defaults_missing_country_to_us(): void
    {
        $context = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([]));

        $this->assertTrue($context->isUs());
        $this->assertSame('TAX', $context->taxable_code);
        $this->assertSame('NON', $context->exempt_code);
        $this->assertSame([], $context->tax_rate_map);
        $this->assertSame([], $context->composite_tax_code_map);
    }

    public function test_from_quickbooks_sync_preserves_non_us_numeric_codes(): void
    {
        $context = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'CA',
            'automatic_taxes' => true,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
        ]));

        $this->assertFalse($context->isUs());
        $this->assertSame('40', $context->taxable_code);
        $this->assertSame('9', $context->exempt_code);
        $this->assertFalse($context->includesTxnTaxDetail());
        $this->assertTrue($context->usesTaxExcludedCalculation());
    }

    public function test_with_maps_preserves_region_flags(): void
    {
        $context = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
        ]))->withMaps(
            [['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40']],
            ['gst:5.0000|pst:7.0000' => 'GST_PST']
        );

        $this->assertSame('CA', $context->country);
        $this->assertSame('40', $context->taxable_code);
        $this->assertSame('9', $context->exempt_code);
        $this->assertSame('GST_PST', $context->composite_tax_code_map['gst:5.0000|pst:7.0000']);
    }

    public function test_normalized_exempt_code_falls_back_for_non_us_literal_non(): void
    {
        $context = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'CA',
            'default_taxable_code' => '40',
            'default_exempt_code' => 'NON',
        ]))->withNormalizedExemptCode();

        $this->assertSame('40', $context->exempt_code);
        $this->assertSame('40', $context->taxable_code);
    }

    public function test_normalized_exempt_code_is_noop_for_us_and_resolved_exempt(): void
    {
        $us = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'US',
        ]))->withNormalizedExemptCode();

        $ca = TaxExportContext::fromQuickbooksSync(new QuickbooksSync([
            'country' => 'CA',
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
        ]))->withNormalizedExemptCode();

        $this->assertSame('NON', $us->exempt_code);
        $this->assertSame('9', $ca->exempt_code);
    }
}

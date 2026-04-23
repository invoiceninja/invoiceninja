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

namespace Tests\Unit\Services\Quickbooks\Transformers;

use App\DataMapper\QuickbooksSettings;
use App\Factory\InvoiceItemFactory;
use App\Models\Company;
use App\Services\Quickbooks\Transformers\ProductTransformer;
use Tests\TestCase;

class ProductTransformerQbTransformTest extends TestCase
{
    public function test_qb_transform_omits_sales_tax_code_for_non_us(): void
    {
        $company = $this->companyWithQbSettings(['country' => 'CA']);

        $line_item = InvoiceItemFactory::create();
        $line_item->tax_id = '1';

        $transformer = new ProductTransformer($company);
        $payload = $transformer->qbTransform($line_item, '30');

        $this->assertArrayNotHasKey('SalesTaxCodeRef', $payload);
    }

    public function test_qb_transform_us_sets_tax(): void
    {
        $company = $this->companyWithQbSettings(['country' => 'US']);

        $line_item = InvoiceItemFactory::create();
        $line_item->tax_id = '1';

        $transformer = new ProductTransformer($company);
        $payload = $transformer->qbTransform($line_item, '30');

        $this->assertSame('TAX', $payload['SalesTaxCodeRef']['value']);
    }

    public function test_qb_transform_us_sets_non_for_exempt_tax_ids(): void
    {
        $company = $this->companyWithQbSettings(['country' => 'US']);

        foreach (['5', '8'] as $tax_id) {
            $line_item = InvoiceItemFactory::create();
            $line_item->tax_id = $tax_id;

            $transformer = new ProductTransformer($company);
            $payload = $transformer->qbTransform($line_item, '30');

            $this->assertSame('NON', $payload['SalesTaxCodeRef']['value'], "tax_id {$tax_id}");
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function companyWithQbSettings(array $settings): Company
    {
        $company = Company::factory()->make();
        $company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => '',
            'refresh_token' => '',
            'realmID' => '',
            'accessTokenExpiresAt' => 0,
            'refreshTokenExpiresAt' => 0,
            'baseURL' => '',
            'companyName' => 'Unit Test',
            'settings' => $settings,
        ]);

        return $company;
    }
}

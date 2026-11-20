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

use App\Models\Client;
use Tests\TestCase;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;

class PdfBuilderShippingDetailsTest extends TestCase
{
    public function testPurchaseOrderShippingAddressNamesTheRecipient(): void
    {
        $elements = (new PdfBuilder($this->pdfService(PdfService::PURCHASE_ORDER)))->shippingDetails();

        $this->assertSame('Ava Thornton', $this->contentOf($elements, 'shipping_address-client.name'));
        $this->assertSame(['shipping_address-label', 'shipping_address-client.name'], array_slice($this->refs($elements), 0, 2));
    }

    public function testInvoiceShippingAddressIsUnchanged(): void
    {
        $elements = (new PdfBuilder($this->pdfService('product')))->shippingDetails();

        $this->assertNotContains('shipping_address-client.name', $this->refs($elements));
        $this->assertSame('shipping_address-client.shipping_address1', $this->refs($elements)[2]);
    }

    private function refs(array $elements): array
    {
        return array_map(fn (array $element) => $element['properties']['data-ref'], $elements);
    }

    private function contentOf(array $elements, string $ref): ?string
    {
        foreach ($elements as $element) {
            if ($element['properties']['data-ref'] === $ref) {
                return $element['content'];
            }
        }

        return null;
    }

    private function pdfService(string $document_type): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $service->document_type = $document_type;
        $service->config = (new \ReflectionClass(PdfConfiguration::class))->newInstanceWithoutConstructor();
        $service->config->client = new Client();
        $service->html_variables = [
            'labels' => [],
            'values' => [
                '$client.name' => 'Ava Thornton',
                '$client.shipping_location_name' => '',
                '$client.shipping_address1' => '4100 Juniper Lane',
                '$client.shipping_address2' => '',
                '$client.shipping_city' => 'Boulder',
                '$client.shipping_state' => 'CO',
                '$client.shipping_postal_code' => '80301',
                '$client.shipping_country' => 'United States',
            ],
        ];

        return $service;
    }
}

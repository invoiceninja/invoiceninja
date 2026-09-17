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

use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;

class JsonDesignPaginationTest extends TestCase
{
    private function reflect(): \ReflectionClass
    {
        return new \ReflectionClass(JsonDesignService::class);
    }

    private function service(): JsonDesignService
    {
        return $this->reflect()->newInstanceWithoutConstructor();
    }

    private function pdfService(): PdfService
    {
        $ps = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $ps->html_variables = ['values' => [], 'labels' => []];

        $cfg = (new \ReflectionClass(PdfConfiguration::class))->newInstanceWithoutConstructor();
        $settings = (new \ReflectionClass(PdfConfiguration::class))->getProperty('settings');
        $settings->setAccessible(true);
        $settings->setValue($cfg, (object) []);

        $config = (new \ReflectionClass(PdfService::class))->getProperty('config');
        $config->setAccessible(true);
        $config->setValue($ps, $cfg);

        return $ps;
    }

    private function setProp(object $obj, string $prop, $value): void
    {
        $p = (new \ReflectionClass($obj))->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function generate(array $design): string
    {
        $ps = $this->pdfService();
        $service = $this->service();
        $this->setProp($service, 'pdfService', $ps);
        $this->setProp($service, 'jsonDesign', $design);
        $this->setProp($service, 'adapter', new JsonToSectionsAdapter($design, $ps));

        $method = $this->reflect()->getMethod('generateBaseTemplate');
        $method->setAccessible(true);

        return $method->invoke($service);
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    private function block(string $id, string $type, int $x, int $y, ?string $region = null, array $properties = []): array
    {
        $block = [
            'id' => $id,
            'type' => $type,
            'gridPosition' => ['x' => $x, 'y' => $y, 'w' => 6, 'h' => 2],
            'properties' => $properties ?: ['content' => $id],
        ];

        if ($region !== null) {
            $block['region'] = $region;
        }

        return $block;
    }

    private function cell(string $html, string $class): string
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]");

        if ($nodes === false || $nodes->length === 0) {
            return '';
        }

        return $dom->saveHTML($nodes->item(0)) ?: '';
    }

    public function testNoPaginationKeepsSingleFlow(): void
    {
        $html = $this->generate([
            'blocks' => [$this->block('notes', 'text', 0, 0)],
        ]);

        $this->assertStringNotContainsString('invoice-pagination', $html);
        $this->assertStringNotContainsString('invoice-page-header', $html);
        $this->assertStringNotContainsString('invoice-page-footer', $html);
        $this->assertStringContainsString('id="notes"', $html);
    }

    public function testPaginationNoneKeepsSingleFlow(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'none'],
            'blocks' => [$this->block('notes', 'text', 0, 0, 'header')],
        ]);

        $this->assertStringNotContainsString('invoice-pagination', $html);
        $this->assertStringContainsString('id="notes"', $html);
    }

    public function testHeaderModeEmitsTheadAndTbodyOnly(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'header', 'headerHeight' => 80],
            'blocks' => [
                $this->block('logo', 'logo', 0, 0, 'header', ['source' => '']),
                $this->block('notes', 'text', 0, 4, 'body'),
            ],
        ]);

        $this->assertStringContainsString('class="invoice-pagination"', $html);
        $this->assertStringContainsString('class="invoice-page-header"', $html);
        $this->assertStringContainsString('class="invoice-page-body"', $html);
        $this->assertStringNotContainsString('class="invoice-page-footer"', $html);

        $header = $this->cell($html, 'invoice-page-header');
        $body = $this->cell($html, 'invoice-page-body');

        $this->assertStringContainsString('id="logo"', $header);
        $this->assertStringNotContainsString('id="notes"', $header);
        $this->assertStringContainsString('id="notes"', $body);
        $this->assertStringNotContainsString('id="logo"', $body);
    }

    public function testFooterModeEmitsTbodyAndTfootOnly(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'footer', 'footerHeight' => 48],
            'blocks' => [
                $this->block('notes', 'text', 0, 0, 'body'),
                $this->block('terms', 'terms', 0, 8, 'footer'),
            ],
        ]);

        $this->assertStringContainsString('class="invoice-page-footer"', $html);
        $this->assertStringContainsString('class="invoice-page-body"', $html);
        $this->assertStringNotContainsString('class="invoice-page-header"', $html);

        $footer = $this->cell($html, 'invoice-page-footer');
        $body = $this->cell($html, 'invoice-page-body');

        $this->assertStringContainsString('id="terms"', $footer);
        $this->assertStringNotContainsString('id="notes"', $footer);
        $this->assertStringContainsString('id="notes"', $body);
        $this->assertStringNotContainsString('id="terms"', $body);
    }

    public function testBothModeEmitsAllThreeSections(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'both'],
            'blocks' => [
                $this->block('logo', 'logo', 0, 0, 'header', ['source' => '']),
                $this->block('notes', 'text', 0, 4, 'body'),
                $this->block('terms', 'terms', 0, 8, 'footer'),
            ],
        ]);

        $this->assertStringContainsString('id="logo"', $this->cell($html, 'invoice-page-header'));
        $this->assertStringContainsString('id="notes"', $this->cell($html, 'invoice-page-body'));
        $this->assertStringContainsString('id="terms"', $this->cell($html, 'invoice-page-footer'));
    }

    public function testHeaderAndBodyAtSameYAreNotTheSameFlexRow(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'both'],
            'blocks' => [
                $this->block('logo', 'logo', 0, 0, 'header', ['source' => '']),
                $this->block('notes', 'text', 6, 0, 'body'),
            ],
        ]);

        $this->assertStringNotContainsString('flex-row', $this->cell($html, 'invoice-page-header'));
        $this->assertStringNotContainsString('flex-row', $this->cell($html, 'invoice-page-body'));
        $this->assertStringContainsString('id="logo"', $this->cell($html, 'invoice-page-header'));
        $this->assertStringContainsString('id="notes"', $this->cell($html, 'invoice-page-body'));
    }

    public function testTableAndTotalsStayInTbody(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'both'],
            'blocks' => [
                $this->block('logo', 'logo', 0, 0, 'header', ['source' => '']),
                $this->block('items', 'table', 0, 4, 'body', ['columns' => [], 'items' => []]),
                $this->block('totals', 'total', 0, 8, 'body', ['items' => []]),
                $this->block('terms', 'terms', 0, 12, 'footer'),
            ],
        ]);

        $body = $this->cell($html, 'invoice-page-body');
        $header = $this->cell($html, 'invoice-page-header');
        $footer = $this->cell($html, 'invoice-page-footer');

        $this->assertStringContainsString('id="items"', $body);
        $this->assertStringContainsString('id="totals"', $body);
        $this->assertStringNotContainsString('id="items"', $header);
        $this->assertStringNotContainsString('id="totals"', $header);
        $this->assertStringNotContainsString('id="items"', $footer);
        $this->assertStringNotContainsString('id="totals"', $footer);
    }

    public function testUnknownAndMissingRegionGoToBody(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'both'],
            'blocks' => [
                $this->block('plain', 'text', 0, 0),
                $this->block('weird', 'text', 0, 2, 'sidebar'),
            ],
        ]);

        $body = $this->cell($html, 'invoice-page-body');

        $this->assertStringContainsString('id="plain"', $body);
        $this->assertStringContainsString('id="weird"', $body);
        $this->assertStringNotContainsString('id="plain"', $this->cell($html, 'invoice-page-header'));
    }

    public function testFooterTaggedBlockLandsInTbodyWhenOnlyHeaderIsOn(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'header'],
            'blocks' => [
                $this->block('logo', 'logo', 0, 0, 'header', ['source' => '']),
                $this->block('terms', 'terms', 0, 8, 'footer'),
            ],
        ]);

        $this->assertStringContainsString('id="terms"', $this->cell($html, 'invoice-page-body'));
        $this->assertStringNotContainsString('class="invoice-page-footer"', $html);
    }

    public function testEmptyHeaderRegionStillReservesMinHeight(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'header', 'headerHeight' => 80],
            'blocks' => [$this->block('notes', 'text', 0, 4, 'body')],
        ]);

        $header = $this->cell($html, 'invoice-page-header');

        $this->assertNotSame('', $header);
        $this->assertStringContainsString('min-height: 80px', $header);
        $this->assertStringNotContainsString('id="notes"', $header);
    }

    public function testPaginationCssAllowsOuterBodyRowToSplit(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'both'],
            'blocks' => [$this->block('notes', 'text', 0, 0, 'body')],
        ]);

        $this->assertStringContainsString('.invoice-pagination > tbody > tr', $html);
        $this->assertStringContainsString('.invoice-widget--table', $html);
    }

    public function testFooterIsPinnedToThePageBottom(): void
    {
        $html = $this->generate([
            'documentSettings' => ['pagination' => 'footer', 'footerHeight' => 64],
            'blocks' => [
                $this->block('notes', 'text', 0, 0, 'body'),
                $this->block('terms', 'terms', 0, 8, 'footer'),
            ],
        ]);

        $this->assertStringContainsString('.invoice-page-footer', $html);
        $this->assertStringContainsString('position: fixed', $html);
        $this->assertStringContainsString('bottom: 0', $html);
        $this->assertStringContainsString('class="invoice-page-footer-space"', $html);
        $this->assertStringContainsString('min-height: 64px', $this->cell($html, 'invoice-page-footer-space'));
        $this->assertStringNotContainsString('id="terms"', $this->cell($html, 'invoice-page-footer-space'));
        $this->assertStringContainsString('id="terms"', $this->cell($html, 'invoice-page-footer'));
    }
}

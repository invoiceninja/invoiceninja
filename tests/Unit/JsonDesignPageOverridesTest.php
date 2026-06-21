<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Services\Pdf\JsonDesignService;
use Tests\TestCase;

/**
 * Body @page rewriter — exercised directly via the public applyPageOverrides
 * method so the test doesn't need a real PdfService / DOMDocument.
 *
 * The rewriter emits the CSS @page keyword form `<paper-name> <orientation>`
 * (e.g. "A4 portrait", "Letter landscape") rather than explicit mm dimensions.
 */
class JsonDesignPageOverridesTest extends TestCase
{
    private function settings(string $pageSize, string $pageLayout = 'portrait'): object
    {
        $s = new \stdClass();
        $s->page_size = $pageSize;
        $s->page_layout = $pageLayout;
        $s->font_size = 12;
        $s->primary_font = 'Inter';

        return $s;
    }

    private function service(): JsonDesignService
    {
        // applyPageOverrides is pure — it reads only the settings argument —
        // so we can build the service without a working constructor by going
        // through reflection.
        return (new \ReflectionClass(JsonDesignService::class))->newInstanceWithoutConstructor();
    }

    private function pageCss(array $design, ?object $settings = null): string
    {
        $service = $this->service();
        $reflection = new \ReflectionClass(JsonDesignService::class);

        $property = $reflection->getProperty('jsonDesign');
        $property->setAccessible(true);
        $property->setValue($service, $design);

        $method = $reflection->getMethod('buildPageCSS');
        $method->setAccessible(true);

        return $method->invoke($service, $design['pageSettings'] ?? [], $settings ?? $this->settings('A4'));
    }

    public function testRewritesA4ToLegal(): void
    {
        $html = '<html><head><style>@page { size: A4; margin: 0; }</style></head><body></body></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('Legal'));

        $this->assertStringContainsString('size: Legal portrait', $result);
        $this->assertStringNotContainsString('size: A4;', $result);
    }

    public function testRewritesLetterToLegal(): void
    {
        $html = '<html><head><style>@page { size: letter; margin: 1in }</style></head><body></body></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('Legal'));

        $this->assertStringContainsString('size: Legal portrait', $result);
    }

    public function testAppliesLandscape(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head><body></body></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('A4', 'landscape'));

        $this->assertStringContainsString('size: A4 landscape', $result);
    }

    public function testInjectsPageRuleWhenBodyHasNone(): void
    {
        $html = '<html><head><meta charset="UTF-8"></head><body></body></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('A4'));

        $this->assertStringContainsString('@page { size: A4 portrait; }', $result);
        $this->assertStringContainsString('</head>', $result);
    }

    public function testLeavesHtmlAloneForUnknownPageSize(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head><body></body></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('SomethingWeird'));

        $this->assertSame($html, $result);
    }

    public function testTabloidIsRecognized(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('Tabloid'));

        $this->assertStringContainsString('size: Tabloid portrait', $result);
    }

    public function testA3LandscapeIsRecognized(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('A3', 'landscape'));

        $this->assertStringContainsString('size: A3 landscape', $result);
    }

    public function testLowercaseInputNormalizesToCanonicalCasing(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head></html>';

        $result = $this->service()->applyPageOverrides($html, $this->settings('letter'));

        $this->assertStringContainsString('size: Letter portrait', $result);
    }

    public function testDocumentSettingsDriveGlobalCssWithoutPageSettings(): void
    {
        $css = $this->pageCss([
            'blocks' => [],
            'documentSettings' => [
                'pageLayout' => 'portrait',
                'pageSize' => 'A4',
                'globalFontSize' => 18,
                'primaryFont' => 'Roboto',
                'pageMarginTop' => 0,
                'pageMarginRight' => 0,
                'pageMarginBottom' => 0,
                'pageMarginLeft' => 0,
                'pagePaddingTop' => 30,
                'pagePaddingRight' => 30,
                'pagePaddingBottom' => 30,
                'pagePaddingLeft' => 30,
            ],
        ]);

        $this->assertStringContainsString('size: A4 portrait;', $css);
        // pageMargin* + pagePadding* collapse into the @page margin so the inset
        // repeats on every page; the container itself carries no padding.
        $this->assertStringContainsString('margin: 30px 30px 30px 30px;', $css);
        $this->assertStringContainsString('padding: 0;', $css);
        $this->assertStringContainsString('font-family: Roboto, Helvetica, sans-serif;', $css);
        $this->assertStringContainsString('font-size: 18px;', $css);
        $this->assertStringNotContainsString('Inter, sans-serif', $css);
        $this->assertStringNotContainsString('font-size: 12px;', $css);
        $this->assertStringNotContainsString('font-size: 18px !important', $css);
    }

    public function testLegacyPageSettingsStillDriveGlobalCssWhenDocumentSettingsAreAbsent(): void
    {
        $css = $this->pageCss([
            'blocks' => [],
            'pageSettings' => [
                'pageSize' => 'letter',
                'orientation' => 'landscape',
                'fontFamily' => 'Legacy Font',
                'fontSize' => '11px',
                'marginTop' => '4mm',
                'marginRight' => '5mm',
                'marginBottom' => '6mm',
                'marginLeft' => '7mm',
            ],
        ], $this->settings('A4'));

        $this->assertStringContainsString('size: 279mm 216mm;', $css);
        $this->assertStringContainsString('margin: 4mm 5mm 6mm 7mm;', $css);
        $this->assertStringContainsString('font-family: Legacy Font, Helvetica, sans-serif;', $css);
        $this->assertStringContainsString('font-size: 11px;', $css);
        $this->assertStringContainsString('padding: 0;', $css);
    }
}

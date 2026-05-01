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

        return $s;
    }

    private function service(): JsonDesignService
    {
        // applyPageOverrides is pure — it reads only the settings argument —
        // so we can build the service without a working constructor by going
        // through reflection.
        return (new \ReflectionClass(JsonDesignService::class))->newInstanceWithoutConstructor();
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
}

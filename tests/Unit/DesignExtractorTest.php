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
use App\Services\Pdf\DesignExtractor;
use Illuminate\Support\Facades\File;

class DesignExtractorTest extends TestCase
{
    private string $temp_designs_path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temp_designs_path = sys_get_temp_dir() . '/design-extractor-tests-' . uniqid() . '/';
        File::ensureDirectoryExists($this->temp_designs_path);

        config(['ninja.designs.base_path' => $this->temp_designs_path]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temp_designs_path);

        parent::tearDown();
    }

    public function testGetSectionHtmlDoesNotThrowWhenDesignFileIsEmpty(): void
    {
        File::put($this->temp_designs_path . 'empty.html', '');

        $extractor = new DesignExtractor('empty');

        $this->assertSame('', $extractor->getSectionHTML('header'));
    }

    public function testGetSectionHtmlDoesNotThrowWhenDesignFileIsMissing(): void
    {
        $extractor = new DesignExtractor('missing');

        $this->assertSame('', $extractor->getSectionHTML('header'));
    }

    public function testGetSectionHtmlExtractsElementFromSetHtml(): void
    {
        $extractor = (new DesignExtractor())->setHtml(
            '<html><body><div id="header"><p>Hello</p></div></body></html>'
        );

        $html = $extractor->getSectionHTML('header');

        $this->assertNotSame('', $html);
        $this->assertStringContainsString('id="header"', $html);
        $this->assertStringContainsString('Hello', $html);
    }

    public function testGetSectionHtmlReturnsEmptyStringWhenSectionIsMissing(): void
    {
        $extractor = (new DesignExtractor())->setHtml(
            '<html><body><div id="header"></div></body></html>'
        );

        $this->assertSame('', $extractor->getSectionHTML('footer'));
    }

    public function testGetSectionHtmlExtractsElementFromDesignFile(): void
    {
        File::put(
            $this->temp_designs_path . 'sample.html',
            '<html><body><div id="company-details">Acme</div></body></html>'
        );

        $extractor = new DesignExtractor('sample');

        $html = $extractor->getSectionHTML('company-details');

        $this->assertStringContainsString('id="company-details"', $html);
        $this->assertStringContainsString('Acme', $html);
    }
}

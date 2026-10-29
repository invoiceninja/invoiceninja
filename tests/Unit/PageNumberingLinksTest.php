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

use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use Tests\Concerns\UsesRealPdfGeneration;
use Tests\TestCase;

/**
 * Set DUMP_PAGE_NUMBERING_PDF=true to write PDFs to tests/artifacts/page-numbering/.
 */
class PageNumberingLinksTest extends TestCase
{
    use PageNumbering;
    use PdfMaker;
    use UsesRealPdfGeneration;

    private bool $dumpPdf;

    private string $artifactDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealPdfGeneration();

        $this->dumpPdf = filter_var(env('DUMP_PAGE_NUMBERING_PDF', false), FILTER_VALIDATE_BOOLEAN);
        $this->artifactDir = base_path('tests/artifacts/page-numbering');

        if ($this->dumpPdf && ! is_dir($this->artifactDir)) {
            mkdir($this->artifactDir, 0755, true);
        }
    }

    private function maybeDumpPdf(string $filename, string $pdf): void
    {
        if (! $this->dumpPdf) {
            return;
        }

        file_put_contents($this->artifactDir.'/'.$filename, $pdf);
    }

    private function sourcePdf(): string
    {
        $html = file_get_contents(base_path('tests/Unit/fixtures/page-numbering-links.html'));

        return $this->makePdf(null, null, $html);
    }

    private function company(): object
    {
        $settings = new \stdClass();
        $settings->page_numbering = true;

        return new class($settings) {
            public function __construct(public object $settings)
            {
            }

            public function getSetting(string $key)
            {
                return 'C';
            }
        };
    }

    public function testPageNumberingKeepsExternalLinks(): void
    {
        $source = $this->sourcePdf();

        $this->assertSame(2, preg_match_all('#/Subtype\s*/Link#', $source));

        $output = $this->pageNumbering($source, $this->company());

        $this->maybeDumpPdf('page-numbering-links-source.pdf', $source);
        $this->maybeDumpPdf('page-numbering-links-numbered.pdf', $output);

        $this->assertSame(2, preg_match_all('#/Subtype\s*/Link#', $output));
        // one link annotation on each of the two pages
        $this->assertSame(2, preg_match_all('#/Type\s*/Page\b.{0,300}?/Annots#s', $output));
        $this->assertStringContainsString('https://example.com/pay', $output);
        $this->assertStringContainsString('https://example.com/view', $output);
    }

    public function testPageNumberingIsSkippedWhenDisabled(): void
    {
        $company = $this->company();
        $company->settings->page_numbering = false;

        $source = $this->sourcePdf();

        $this->assertSame($source, $this->pageNumbering($source, $company));
    }
}

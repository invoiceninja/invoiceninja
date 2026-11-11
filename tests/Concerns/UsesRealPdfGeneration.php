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

namespace Tests\Concerns;

/**
 * Opt out of PDF_GENERATOR=simulator for tests that require real HTML→PDF output.
 *
 * Set PDF_GENERATOR_REAL in CI (default snappdf; run vendor/bin/snappdf download).
 * ZUGFeRD PDF/XML merge tests that build PDFs via FPDF or ZugferdPdfMerger directly
 * do not need this trait.
 */
trait UsesRealPdfGeneration
{
    protected function useRealPdfGeneration(): void
    {
        config(['ninja.pdf_generator' => config('ninja.pdf_generator_real')]);
    }
}

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

use App\Models\Invoice;
use Tests\TestCase;

class JapaneseFilenameTest extends TestCase
{
    protected function tearDown(): void
    {
        app()->setLocale('en');

        parent::tearDown();
    }

    public function test_japanese_filename_is_preserved(): void
    {
        app()->setLocale('ja');

        $invoice = new Invoice();
        $invoice->number = '0354';

        $this->assertSame('請求書_0354', $invoice->numberFormatter());
        $this->assertSame('請求書_0354.pdf', $invoice->getFileName());
    }

    public function test_ascii_filename_is_unchanged(): void
    {
        app()->setLocale('en');

        $invoice = new Invoice();
        $invoice->number = '0354';

        $this->assertSame('Invoice_0354', $invoice->numberFormatter());
        $this->assertSame('Invoice_0354.pdf', $invoice->getFileName());
    }
}

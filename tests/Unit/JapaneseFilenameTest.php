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
use Symfony\Component\HttpFoundation\HeaderUtils;
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
        $this->assertSame('配達メモ_請求書_0354.pdf', $invoice->getDeliveryNoteName());
    }

    public function test_content_disposition_uses_utf8_filename(): void
    {
        app()->setLocale('ja');

        $invoice = new Invoice();
        $invoice->number = '0354';

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $invoice->getFileName(),
            \Illuminate\Support\Str::ascii($invoice->getFileName())
        );

        $this->assertStringContainsString(
            "filename*=utf-8''%E8%AB%8B%E6%B1%82%E6%9B%B8_0354.pdf",
            $disposition
        );
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

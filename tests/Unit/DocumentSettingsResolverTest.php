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

use App\Services\Pdf\DocumentSettingsResolver;
use Tests\TestCase;

class DocumentSettingsResolverTest extends TestCase
{
    private function baseSettings(): object
    {
        $s = new \stdClass();
        $s->page_size = 'A4';
        $s->page_layout = 'portrait';
        $s->font_size = 14;
        $s->primary_font = 'Roboto';
        $s->secondary_font = 'Roboto';
        $s->show_paid_stamp = true;
        $s->show_shipping_address = true;
        $s->embed_documents = true;
        $s->hide_empty_columns_on_pdf = true;
        $s->page_numbering = true;
        $s->unrelated = 'untouched';

        return $s;
    }

    public function testNoDocumentSettingsReportsNoOverrides(): void
    {
        $resolver = new DocumentSettingsResolver(['blocks' => []], $this->baseSettings());

        $this->assertFalse($resolver->hasOverrides());
    }

    public function testEmptyDocumentSettingsReportsNoOverrides(): void
    {
        $resolver = new DocumentSettingsResolver(['documentSettings' => []], $this->baseSettings());

        $this->assertFalse($resolver->hasOverrides());
    }

    public function testOverridesAreApplied(): void
    {
        $design = [
            'documentSettings' => [
                'pageSize' => 'legal',
                'pageLayout' => 'landscape',
                'globalFontSize' => 16,
                'primaryFont' => 'Open_Sans',
                'secondaryFont' => 'Lato',
                'showPaidStamp' => false,
                'showShippingAddress' => false,
                'embedDocuments' => false,
                'hideEmptyColumns' => false,
                'pageNumbering' => false,
            ],
        ];

        $resolved = (new DocumentSettingsResolver($design, $this->baseSettings()))->resolve();

        $this->assertSame('legal', $resolved->page_size);
        $this->assertSame('landscape', $resolved->page_layout);
        $this->assertSame(16, $resolved->font_size);
        $this->assertSame('Open_Sans', $resolved->primary_font);
        $this->assertSame('Lato', $resolved->secondary_font);
        $this->assertFalse($resolved->show_paid_stamp);
        $this->assertFalse($resolved->show_shipping_address);
        $this->assertFalse($resolved->embed_documents);
        $this->assertFalse($resolved->hide_empty_columns_on_pdf);
        $this->assertFalse($resolved->page_numbering);
    }

    public function testFalseOverrideBeatsTrueDefault(): void
    {
        $design = ['documentSettings' => ['showPaidStamp' => false]];

        $resolved = (new DocumentSettingsResolver($design, $this->baseSettings()))->resolve();

        $this->assertFalse($resolved->show_paid_stamp);
    }

    public function testAbsentFieldFallsBackToCompanySetting(): void
    {
        $design = ['documentSettings' => ['pageSize' => 'letter']];

        $resolved = (new DocumentSettingsResolver($design, $this->baseSettings()))->resolve();

        $this->assertSame('letter', $resolved->page_size);
        $this->assertSame('portrait', $resolved->page_layout);
        $this->assertTrue($resolved->show_paid_stamp);
        $this->assertSame('Roboto', $resolved->primary_font);
    }

    public function testResolveReturnsCloneAndDoesNotMutateOriginal(): void
    {
        $original = $this->baseSettings();
        $design = ['documentSettings' => ['pageSize' => 'legal', 'showPaidStamp' => false]];

        $resolved = (new DocumentSettingsResolver($design, $original))->resolve();

        $this->assertNotSame($original, $resolved);
        $this->assertSame('A4', $original->page_size);
        $this->assertTrue($original->show_paid_stamp);
        $this->assertSame('legal', $resolved->page_size);
        $this->assertFalse($resolved->show_paid_stamp);
    }

    public function testUnrelatedFieldsArePreservedOnTheClone(): void
    {
        $design = ['documentSettings' => ['pageSize' => 'legal']];

        $resolved = (new DocumentSettingsResolver($design, $this->baseSettings()))->resolve();

        $this->assertSame('untouched', $resolved->unrelated);
    }
}

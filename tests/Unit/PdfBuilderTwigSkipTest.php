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

use DOMDocument;
use Tests\TestCase;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;

class PdfBuilderTwigSkipTest extends TestCase
{
    public function testParseTwigElementsReturnsBeforeTemplateSetupWhenNoNinjaNodes(): void
    {
        $builder = new PdfBuilder(
            (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor()
        );

        $document = new DOMDocument();
        @$document->loadHTML('<!DOCTYPE html><html><body><p>No twig here</p></body></html>');
        $builder->setDocument($document);

        $method = (new \ReflectionClass(PdfBuilder::class))->getMethod('parseTwigElements');

        $this->assertSame($builder, $method->invoke($builder));
        $this->assertStringContainsString('No twig here', $builder->document->saveHTML());
    }
}

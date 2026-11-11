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

use App\Models\Product;
use App\Utils\Helpers;
use Carbon\Carbon;
use Tests\TestCase;

class ProductNotesTest extends TestCase
{
    public function testMarkdownHelpProcessesReservedKeywordsWhenEntityProvided(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 3, 15, 0, 0, 0, 'UTC'));

        $entity = $this->makeEntity();

        $expected = Helpers::processReservedKeywords('Billing period: :MONTH :YEAR', $entity);
        $result = strip_tags(Product::markdownHelp('Billing period: :MONTH :YEAR', $entity));

        $this->assertSame(trim($expected), trim($result));

        Carbon::setTestNow();
    }

    public function testMarkdownHelpWithoutEntitySkipsReservedKeywordProcessing(): void
    {
        $result = strip_tags(Product::markdownHelp('Billing period: :MONTH :YEAR'));

        $this->assertSame('Billing period: :MONTH :YEAR', trim($result));
    }

    public function testMarkdownNotesProcessesReservedKeywords(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 3, 15, 0, 0, 0, 'UTC'));

        $entity = $this->makeEntity();
        $product = new Product(['notes' => 'Service for :MONTHYEAR']);

        $expected = Helpers::processReservedKeywords('Service for :MONTHYEAR', $entity);
        $result = strip_tags($product->markdownNotes($entity));

        $this->assertSame(trim($expected), trim($result));

        Carbon::setTestNow();
    }

    private function makeEntity(): object
    {
        return new class {
            public function locale(): string
            {
                return 'en';
            }

            public function timezone(): object
            {
                return (object) ['name' => 'UTC'];
            }

            public function date_format(): string
            {
                return 'Y-m-d';
            }
        };
    }
}

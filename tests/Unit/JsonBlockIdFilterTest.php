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

use App\Services\Pdf\JsonToSectionsAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Block ids are interpolated into HTML `id` attributes and `data-ref`
 * strings — anything that breaks out of an attribute would mangle the DOM
 * and (although Purify::clean strips active script content downstream)
 * cause the setSections() id-keyed injection to misfire.
 *
 * filterValidBlocks() is the single source of truth for what counts as a
 * safe id; this test pins that contract.
 */
class JsonBlockIdFilterTest extends TestCase
{
    public function testAcceptsKebabAndUuidIds(): void
    {
        $blocks = [
            ['id' => 'company-info', 'type' => 'company-info'],
            ['id' => 'client-shipping-info-4cf20811-3ae1-48b5-b37a-4d84a3b53cba', 'type' => 'x'],
            ['id' => 'invoice_details_01', 'type' => 'x'],
            ['id' => 'block.with.dots', 'type' => 'x'],
        ];

        $this->assertCount(4, JsonToSectionsAdapter::filterValidBlocks($blocks));
    }

    #[DataProvider('dangerousIds')]
    public function testRejectsDangerousIds(mixed $id): void
    {
        $blocks = [['id' => $id, 'type' => 'company-info']];

        $this->assertSame([], JsonToSectionsAdapter::filterValidBlocks($blocks));
    }

    public static function dangerousIds(): array
    {
        return [
            'attribute breakout double quote'   => ['"><script>alert(1)</script>'],
            'attribute breakout single quote'   => ["' onclick='x"],
            'angle bracket'                     => ['<script>'],
            'space'                             => ['has space'],
            'tab'                               => ["with\ttab"],
            'newline'                           => ["with\nnewline"],
            'ampersand'                         => ['a&b'],
            'forward slash'                     => ['a/b'],
            'backslash'                         => ['a\\b'],
            'empty string'                      => [''],
            'null'                              => [null],
            'non-string array'                  => [['nested']],
            'too long'                          => [str_repeat('a', 129)],
        ];
    }

    public function testReindexesAfterFiltering(): void
    {
        $blocks = [
            ['id' => 'good-1',     'type' => 'x'],
            ['id' => '<bad>',      'type' => 'x'],
            ['id' => 'good-2',     'type' => 'x'],
        ];

        $filtered = JsonToSectionsAdapter::filterValidBlocks($blocks);

        $this->assertCount(2, $filtered);
        $this->assertSame([0, 1], array_keys($filtered));
        $this->assertSame('good-1', $filtered[0]['id']);
        $this->assertSame('good-2', $filtered[1]['id']);
    }

    public function testDropsEntriesMissingId(): void
    {
        $blocks = [
            ['type' => 'no-id-key'],
            ['id' => 'has-id', 'type' => 'x'],
        ];

        $filtered = JsonToSectionsAdapter::filterValidBlocks($blocks);

        $this->assertCount(1, $filtered);
        $this->assertSame('has-id', $filtered[0]['id']);
    }
}

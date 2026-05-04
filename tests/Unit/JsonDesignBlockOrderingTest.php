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

use App\Models\Company;
use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\JsonToSectionsAdapter;

class JsonDesignBlockOrderingTest extends TestCase
{
    public function testRowGroupingUsesCachedSortedBlocks(): void
    {
        $adapter = new JsonToSectionsAdapter([
            'blocks' => [
                $this->block('second', 6, 2),
                $this->block('first-right', 4, 1),
                $this->block('first-left', 0, 1),
            ],
        ], $this->pdfService());

        $rows = $adapter->getRowGroupedBlocks();

        $this->assertSame(['first-left', 'first-right'], array_column($rows[0], 'id'));
        $this->assertSame(['second'], array_column($rows[1], 'id'));

        $ref = new \ReflectionClass($adapter);

        $sortedBlocks = $ref->getProperty('sortedBlocks')->getValue($adapter);
        $blocksByRow = $ref->getProperty('blocksByRow')->getValue($adapter);

        $this->assertNotNull($sortedBlocks);
        $this->assertNotNull($blocksByRow);
        $this->assertSame(['first-left', 'first-right', 'second'], array_column($sortedBlocks, 'id'));
    }

    private function block(string $id, int $x, int $y): array
    {
        return [
            'id' => $id,
            'type' => 'spacer',
            'gridPosition' => ['x' => $x, 'y' => $y, 'w' => 1, 'h' => 1],
            'properties' => ['height' => '1px'],
        ];
    }

    private function pdfService(): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();

        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;

        return $service;
    }
}

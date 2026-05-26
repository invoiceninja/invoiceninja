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
use App\Services\Pdf\JsonToSectionsAdapter;
use App\Services\Pdf\PdfService;
use Tests\TestCase;

class JsonToSectionsSemanticBlocksTest extends TestCase
{
    public function test_terms_footer_and_public_notes_blocks_map_to_text_sections(): void
    {
        $design = [
            'pageSettings' => [],
            'blocks' => [
                [
                    'id' => 'public-notes-uuid',
                    'type' => 'public-notes',
                    'gridPosition' => ['x' => 0, 'y' => 14, 'w' => 6, 'h' => 3],
                    'properties' => [
                        'content' => '$public_notes',
                        'fontWeight' => 'normal',
                        'lineHeight' => '1.3',
                        'align' => 'left',
                        'color' => '#000000',
                    ],
                ],
                [
                    'id' => 'terms-uuid',
                    'type' => 'terms',
                    'gridPosition' => ['x' => 0, 'y' => 17, 'w' => 12, 'h' => 2],
                    'properties' => [
                        'content' => '$terms',
                        'align' => 'left',
                        'color' => '#000000',
                    ],
                ],
                [
                    'id' => 'footer-uuid',
                    'type' => 'footer',
                    'gridPosition' => ['x' => 0, 'y' => 19, 'w' => 12, 'h' => 2],
                    'properties' => [
                        'content' => '$footer',
                        'align' => 'center',
                        'color' => '#6B7280',
                    ],
                ],
            ],
        ];

        $sections = (new JsonToSectionsAdapter($design, $this->minimalPdfService()))->toSections();

        $this->assertArrayHasKey('public-notes-uuid', $sections);
        $this->assertSame('$public_notes', $sections['public-notes-uuid']['elements'][0]['content']);

        $this->assertArrayHasKey('terms-uuid', $sections);
        $this->assertSame('$terms', $sections['terms-uuid']['elements'][0]['content']);

        $this->assertArrayHasKey('footer-uuid', $sections);
        $this->assertSame('$footer', $sections['footer-uuid']['elements'][0]['content']);
    }

    private function minimalPdfService(): PdfService
    {
        $service = (new \ReflectionClass(PdfService::class))->newInstanceWithoutConstructor();
        $company = new Company();
        $company->company_key = 'test-company';
        $service->company = $company;
        $service->html_variables = [
            'labels' => [],
            'values' => [],
        ];

        return $service;
    }
}

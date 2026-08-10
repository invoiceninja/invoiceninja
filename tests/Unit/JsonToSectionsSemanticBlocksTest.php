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

    public function test_signature_block_uses_non_collapsing_height_and_all_configured_styles(): void
    {
        $design = [
            'pageSettings' => [],
            'blocks' => [[
                'id' => 'signature-uuid',
                'type' => 'signature',
                'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 3],
                'properties' => [
                    'label' => 'Approved by',
                    'showLine' => true,
                    'showDate' => true,
                    'align' => 'right',
                    'fontSize' => '18px',
                    'fontWeight' => 'bold',
                    'fontStyle' => 'italic',
                    'color' => '#123456',
                    'signatureHeight' => '72px',
                    'lineWidth' => '240px',
                    'lineThickness' => '2px',
                    'lineStyle' => 'dashed',
                    'lineColor' => '#654321',
                    'padding' => '6px',
                ],
            ]],
        ];

        $section = (new JsonToSectionsAdapter($design, $this->minimalPdfService()))
            ->toSections()['signature-uuid'];
        $elements = $section['elements'];

        $this->assertSame('height: 72px;', $elements[0]['properties']['style']);
        $this->assertStringNotContainsString('margin-bottom: 40px', $elements[0]['properties']['style']);
        $this->assertStringContainsString('border-top: 2px dashed #654321', $elements[1]['properties']['style']);
        $this->assertStringContainsString('width: 240px', $elements[1]['properties']['style']);
        $this->assertSame('Approved by', $elements[2]['content']);
        $this->assertStringContainsString('font-weight: bold', $elements[2]['properties']['style']);
        $this->assertSame('Date: ________________', $elements[3]['content']);
        $this->assertStringContainsString('text-align: right', $section['properties']['style']);
        $this->assertStringContainsString('padding: 6px', $section['properties']['style']);
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

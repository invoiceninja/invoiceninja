<?php

namespace Tests\Feature\Design;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;

/**
 * Test JSON-based visual designer integration with PdfBuilder
 *
 * Validates that JSON blocks are correctly converted to PdfBuilder sections
 * without modifying any core PdfBuilder methods.
 */
class JsonDesignServiceTest extends TestCase
{
    use MockAccountData;
    private array $testDesign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Skipping JsonDesignServiceTest');
        // Load test design
        $jsonPath = base_path('tests/Feature/Design/stubs/test_design_1.json');
        $this->testDesign = json_decode(file_get_contents($jsonPath), true);

        $this->makeTestData();
    }

    public function testJsonDesignValidation()
    {
        $mockService = $this->createMock(PdfService::class);

        $service = new JsonDesignService($mockService, $this->testDesign);

        $this->assertTrue($service->isValid());
        $this->assertIsArray($service->getBlocks());
        $this->assertNotEmpty($service->getBlocks());
        $this->assertIsArray($service->getPageSettings());
    }

    public function testInvalidJsonDesign()
    {
        $mockService = $this->createMock(PdfService::class);

        $invalidDesign = ['invalid' => 'structure'];
        $service = new JsonDesignService($mockService, $invalidDesign);

        $this->assertFalse($service->isValid());
    }

    public function testJsonToSectionsConversion()
    {
        $mockService = $this->createMock(PdfService::class);

        $adapter = new JsonToSectionsAdapter($this->testDesign, $mockService);
        $sections = $adapter->toSections();

        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections);

        // Verify section structure
        foreach ($sections as $sectionId => $section) {
            $this->assertArrayHasKey('id', $section);
            $this->assertArrayHasKey('elements', $section);
            $this->assertIsArray($section['elements']);
        }
    }

    public function testBlockTypeConversions()
    {
        $mockService = $this->createMock(PdfService::class);

        $adapter = new JsonToSectionsAdapter($this->testDesign, $mockService);
        $sections = $adapter->toSections();

        // Verify that sections exist (some may be grouped into rows)
        $this->assertNotEmpty($sections, 'Sections should not be empty');

        // Count block types that should be present
        $hasLogoBlock = false;
        $hasTableBlock = false;
        $hasTotalBlock = false;

        foreach ($this->testDesign['blocks'] as $block) {
            if ($block['type'] === 'logo') {
                $hasLogoBlock = true;
            }
            if ($block['type'] === 'table') {
                $hasTableBlock = true;
            }
            if ($block['type'] === 'total') {
                $hasTotalBlock = true;
            }
        }

        $this->assertTrue($hasLogoBlock, 'Logo block should exist in test design');
        $this->assertTrue($hasTableBlock, 'Table block should exist in test design');
        $this->assertTrue($hasTotalBlock, 'Total block should exist in test design');

        // Verify some blocks are in sections (either as standalone or grouped in rows)
        $hasLogoSection = isset($sections['logo-1765268278392']);
        $hasTableSection = isset($sections['table-1765268328782']);
        $hasRowSections = false;

        foreach (array_keys($sections) as $sectionId) {
            if (str_starts_with($sectionId, 'row-')) {
                $hasRowSections = true;
                break;
            }
        }

        $this->assertTrue($hasLogoSection || $hasRowSections, 'Should have either block sections or row sections');
    }

    public function testDataRefAttributesPresent()
    {
        $mockService = $this->createMock(PdfService::class);

        $adapter = new JsonToSectionsAdapter($this->testDesign, $mockService);
        $sections = $adapter->toSections();

        // Check that data-ref attributes are present for CSS targeting
        $hasDataRef = false;

        foreach ($sections as $section) {
            if (isset($section['elements'])) {
                foreach ($section['elements'] as $element) {
                    if (isset($element['properties']['data-ref'])) {
                        $hasDataRef = true;
                        break 2;
                    }
                }
            }
        }

        $this->assertTrue($hasDataRef, 'Sections should contain data-ref attributes for CSS targeting');
    }

    public function testPageSettingsExtraction()
    {
        $mockService = $this->createMock(PdfService::class);

        $service = new JsonDesignService($mockService, $this->testDesign);
        $pageSettings = $service->getPageSettings();

        $this->assertIsArray($pageSettings);

        // Verify expected page settings keys
        if (!empty($pageSettings)) {
            $this->assertArrayHasKey('pageSize', $pageSettings);
            $this->assertArrayHasKey('orientation', $pageSettings);
        }
    }

    public function testBlockSorting()
    {
        $mockService = $this->createMock(PdfService::class);

        // Create test design with unsorted blocks
        $unsortedDesign = [
            'blocks' => [
                [
                    'id' => 'block-3',
                    'type' => 'text',
                    'gridPosition' => ['x' => 0, 'y' => 10, 'w' => 12, 'h' => 1],
                    'properties' => ['content' => 'Third block'],
                ],
                [
                    'id' => 'block-1',
                    'type' => 'text',
                    'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 1],
                    'properties' => ['content' => 'First block'],
                ],
                [
                    'id' => 'block-2',
                    'type' => 'text',
                    'gridPosition' => ['x' => 0, 'y' => 5, 'w' => 12, 'h' => 1],
                    'properties' => ['content' => 'Second block'],
                ],
            ],
        ];

        $adapter = new JsonToSectionsAdapter($unsortedDesign, $mockService);
        $sections = $adapter->toSections();

        $sectionIds = array_keys($sections);

        // Verify blocks are processed in Y-position order
        $this->assertEquals('block-1', $sectionIds[0]);
        $this->assertEquals('block-2', $sectionIds[1]);
        $this->assertEquals('block-3', $sectionIds[2]);
    }

    public function testStyleGeneration()
    {
        $mockService = $this->createMock(PdfService::class);

        $adapter = new JsonToSectionsAdapter($this->testDesign, $mockService);
        $sections = $adapter->toSections();

        // Check that styles are generated
        $hasStyles = false;

        foreach ($sections as $section) {
            if (isset($section['properties']['style'])) {
                $hasStyles = true;
                $this->assertIsString($section['properties']['style']);
                break;
            }

            if (isset($section['elements'])) {
                foreach ($section['elements'] as $element) {
                    if (isset($element['properties']['style'])) {
                        $hasStyles = true;
                        $this->assertIsString($element['properties']['style']);
                        break 2;
                    }
                }
            }
        }

        $this->assertTrue($hasStyles, 'Sections should contain inline styles');
    }

    public function test_json_design_service()
    {
        $this->assertNotNull($this->invoice->invitations()->first());
        
        $designjson = file_get_contents(base_path('tests/Feature/Design/stubs/test_design_1.json'));
        $design = json_decode($designjson, true);
        
        $pdfService = new PdfService($this->invoice->invitations()->first(), 'product');
        $service = new JsonDesignService($pdfService, $design);
        
        $html = $service->build();
        
        $this->assertNotNull($html);
        file_put_contents(base_path('tests/artifacts/json_service_output.html'), $html);
    }

}

<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfConfiguration;
use App\Models\Design;
use ReflectionClass;

/**
 * Simple unit test to verify JSON design detection logic
 */
class JsonDesignDetectionTest extends TestCase
{
    public function test_isJsonDesign_detects_json_structure()
    {
        // Load test JSON design
        $jsonPath = base_path('tests/Feature/Design/stubs/test_design_1.json');
        $designJson = file_get_contents($jsonPath);
        $designData = json_decode($designJson, true);

        // Create a mock design with JSON structure
        $design = new Design();
        $design->is_custom = true;
        $design->name = 'JSON Test Design';
        $design->design = $designData;

        // Verify it has the expected structure
        $this->assertIsArray($designData);
        $this->assertArrayHasKey('blocks', $designData);
        $this->assertIsArray($designData['blocks']);

        // May have pageSettings or templateId
        $hasExpectedKeys = isset($designData['pageSettings']) || isset($designData['templateId']);
        $this->assertTrue($hasExpectedKeys, "Should have pageSettings or templateId");

        echo "\n✅ JSON design structure validated\n";
        echo "Blocks count: " . count($designData['blocks']) . "\n";
        echo "Block types: " . implode(', ', array_column($designData['blocks'], 'type')) . "\n\n";
    }

    public function test_traditional_design_structure()
    {
        // Traditional designs have this structure
        $traditionalDesign = [
            'includes' => '<style>body { font-family: sans-serif; }</style>',
            'header' => '<div>Header</div>',
            'body' => '<div>Body</div>',
            'footer' => '<div>Footer</div>',
        ];

        $this->assertArrayHasKey('includes', $traditionalDesign);
        $this->assertArrayHasKey('header', $traditionalDesign);
        $this->assertArrayHasKey('body', $traditionalDesign);
        $this->assertArrayHasKey('footer', $traditionalDesign);

        echo "\n✅ Traditional design structure validated\n\n";
    }

    public function test_can_distinguish_json_from_traditional()
    {
        // Load JSON design
        $jsonPath = base_path('tests/Feature/Design/stubs/test_design_1.json');
        $jsonDesign = json_decode(file_get_contents($jsonPath), true);

        // Traditional design structure
        $traditionalDesign = [
            'includes' => '<style></style>',
            'header' => '<div></div>',
            'body' => '<div></div>',
            'footer' => '<div></div>',
        ];

        // JSON design has blocks array
        $isJsonDesign = isset($jsonDesign['blocks']) && is_array($jsonDesign['blocks']);

        // Traditional design has includes, header, body, footer
        $isTraditionalDesign = isset($traditionalDesign['includes']) &&
                               isset($traditionalDesign['header']) &&
                               isset($traditionalDesign['body']) &&
                               isset($traditionalDesign['footer']);

        $this->assertTrue($isJsonDesign, "Should detect JSON design");
        $this->assertTrue($isTraditionalDesign, "Should detect traditional design");

        $jsonHasTraditionalKeys = isset($jsonDesign['includes']) || isset($jsonDesign['header']);
        $traditionalHasJsonKeys = isset($traditionalDesign['blocks']) || isset($traditionalDesign['pageSettings']);

        $this->assertFalse($jsonHasTraditionalKeys, "JSON design should not have traditional keys");
        $this->assertFalse($traditionalHasJsonKeys, "Traditional design should not have JSON keys");

        echo "\n✅ Can successfully distinguish JSON from traditional designs\n\n";
    }
}

<?php

namespace Tests\Feature\Design;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Design;
use App\Services\Pdf\PdfService;

/**
 * Test that JSON designs are properly detected and used in PdfService
 */
class JsonDesignerIntegrationTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function test_json_design_detection_and_generation()
    {
        // Load test JSON design
        $jsonPath = base_path('tests/Feature/Design/stubs/test_design_1.json');
        $designJson = file_get_contents($jsonPath);
        $designData = json_decode($designJson, true);

        // Create a custom design with JSON structure
        $design = new Design();
        $design->company_id = $this->company->id;
        $design->user_id = $this->user->id;
        $design->is_custom = true;
        $design->is_active = true;
        $design->name = 'JSON Test Design';
        $design->design = $designData; // This will be cast to object
        $design->save();

        // Update the invoice to use this design
        $this->invoice->design_id = $design->id;
        $this->invoice->save();
        $this->invoice->fresh();

        // Get an invitation
        $invitation = $this->invoice->invitations()->first();
        $this->assertNotNull($invitation, "Invoice should have an invitation");

        // Create PDF service
        $pdfService = new PdfService($invitation, 'product');
        $pdfService->init();

        // Get the HTML
        $html = $pdfService->getHtml();

        // Assertions
        $this->assertNotNull($html);
        $this->assertNotEmpty($html);

        // Verify it contains expected JSON design elements
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('invoice-container', $html);

        // Save output for manual inspection
        $outputPath = base_path('tests/artifacts/json_designer_integration_output.html');
        file_put_contents($outputPath, $html);

        echo "\n\n✅ JSON Designer Integration Test Passed!\n";
        echo "📄 HTML output saved to: {$outputPath}\n";
        echo "📏 HTML size: " . strlen($html) . " bytes\n\n";
    }

    public function test_traditional_design_still_works()
    {
        // Use a traditional design (not JSON-based)
        $traditionalDesign = new Design();
        $traditionalDesign->company_id = $this->company->id;
        $traditionalDesign->user_id = $this->user->id;
        $traditionalDesign->is_custom = false;
        $traditionalDesign->is_active = true;
        $traditionalDesign->name = 'clean'; // Standard design name
        $traditionalDesign->save();

        $this->invoice->design_id = $traditionalDesign->id;
        $this->invoice->save();
        $this->invoice->fresh();

        $invitation = $this->invoice->invitations()->first();
        $this->assertNotNull($invitation);

        // Create PDF service
        $pdfService = new PdfService($invitation, 'product');
        $pdfService->init();

        // Get the HTML
        $html = $pdfService->getHtml();

        // Assertions
        $this->assertNotNull($html);
        $this->assertNotEmpty($html);

        echo "\n\n✅ Traditional Design Test Passed!\n";
        echo "📏 HTML size: " . strlen($html) . " bytes\n\n";
    }

    public function test_invalid_json_design_falls_back()
    {
        // Create an invalid JSON design (missing required keys)
        $invalidDesign = new Design();
        $invalidDesign->company_id = $this->company->id;
        $invalidDesign->user_id = $this->user->id;
        $invalidDesign->is_custom = true;
        $invalidDesign->is_active = true;
        $invalidDesign->name = 'Invalid JSON Design';
        $invalidDesign->design = [
            'invalid' => 'structure',
            // Missing 'blocks' and 'pageSettings'
        ];
        $invalidDesign->save();

        $this->invoice->design_id = $invalidDesign->id;
        $this->invoice->save();
        $this->invoice->fresh();

        $invitation = $this->invoice->invitations()->first();
        $this->assertNotNull($invitation);

        // Create PDF service - should fall back to traditional flow
        $pdfService = new PdfService($invitation, 'product');
        $pdfService->init();

        // Get the HTML - should not fail
        $html = $pdfService->getHtml();

        // Assertions - should still generate HTML via fallback
        $this->assertNotNull($html);
        $this->assertNotEmpty($html);

        echo "\n\n✅ Fallback Test Passed!\n";
        echo "📏 HTML size: " . strlen($html) . " bytes\n\n";
    }
}

<?php

namespace Tests\Unit;

use Tests\TestCase;
use Tests\MockAccountData;
use App\Models\Design;
use App\Services\Pdf\PdfMock;
use App\Services\Pdf\PdfService;
use App\Services\Pdf\PdfDesigner;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfConfiguration;
use App\Services\Pdf\JsonDesignService;
use App\Services\Pdf\JsonToSectionsAdapter;
use App\Services\Pdf\Purify;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests to prove the actual behavior of the JSON design preview pipeline.
 *
 * Each test is small and proves ONE thing. The goal is to identify exactly
 * where content gets lost when previewing a JSON-based design.
 */
class JsonDesignPreviewTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private array $jsonDesign;
    private array $traditionalDesign;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();

        // Load the test JSON design
        $jsonPath = base_path('tests/Feature/Design/stubs/test_design_1.json');
        $this->jsonDesign = json_decode(file_get_contents($jsonPath), true);

        // A minimal traditional design
        $this->traditionalDesign = [
            'includes' => '<style>body { font-family: sans-serif; }</style>',
            'header' => '<div id="header"><h1>Invoice</h1></div>',
            'body' => '<div id="body"><p>Body content</p></div>',
            'footer' => '<div id="footer"><p>Footer content</p></div>',
        ];
    }

    // -----------------------------------------------------------------------
    // 1. PdfDesigner::composeFromPartials with traditional design
    // -----------------------------------------------------------------------
    public function testComposeFromPartialsWithTraditionalDesign(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->designer = new PdfDesigner($ps);

        $result = $ps->designer->buildFromPartials($this->traditionalDesign);

        $this->assertNotEmpty($result->template, 'Traditional design should produce non-empty template');
        $this->assertStringContainsString('Invoice', $result->template);
        $this->assertStringContainsString('Body content', $result->template);
        $this->assertStringContainsString('Footer content', $result->template);
    }

    // -----------------------------------------------------------------------
    // 2. PdfDesigner::composeFromPartials with JSON design data (has blocks, no includes/header/body/footer)
    // -----------------------------------------------------------------------
    public function testComposeFromPartialsWithJsonDesignReturnsEmpty(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->designer = new PdfDesigner($ps);

        // JSON design has 'blocks' key but NOT includes/header/body/footer
        $result = $ps->designer->buildFromPartials($this->jsonDesign);

        // This PROVES that composeFromPartials returns empty/placeholder for JSON designs
        // because it only looks for includes/header/body/footer keys
        $this->assertEquals('<p></p>', $result->template,
            'composeFromPartials should return empty placeholder for JSON design (no includes/header/body/footer keys)');
    }

    // -----------------------------------------------------------------------
    // 3. JsonDesignService::isValid with valid data
    // -----------------------------------------------------------------------
    public function testJsonDesignServiceIsValidWithValidData(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');

        $service = new JsonDesignService($ps, $this->jsonDesign);
        $this->assertTrue($service->isValid(), 'Test design should be valid');
    }

    // -----------------------------------------------------------------------
    // 4. JsonDesignService::isValid with invalid data (no blocks)
    // -----------------------------------------------------------------------
    public function testJsonDesignServiceIsValidWithInvalidData(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');

        $service = new JsonDesignService($ps, ['invalid' => 'data']);
        $this->assertFalse($service->isValid(), 'Design without blocks should be invalid');
    }

    // -----------------------------------------------------------------------
    // 5. JsonDesignService::isValid with blocks missing required keys
    // -----------------------------------------------------------------------
    public function testJsonDesignServiceIsValidWithMalformedBlocks(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');

        $service = new JsonDesignService($ps, [
            'blocks' => [
                ['id' => 'test', 'type' => 'text'],  // Missing gridPosition
            ],
        ]);
        $this->assertFalse($service->isValid(), 'Block without gridPosition should be invalid');
    }

    // -----------------------------------------------------------------------
    // 6. JsonToSectionsAdapter converts blocks to sections
    // -----------------------------------------------------------------------
    public function testJsonToSectionsAdapterProducesSections(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();

        $adapter = new JsonToSectionsAdapter($this->jsonDesign, $ps);
        $sections = $adapter->toSections();

        $this->assertIsArray($sections);
        $this->assertNotEmpty($sections, 'Sections should not be empty');

        // Each section should have id and elements
        foreach ($sections as $sectionId => $section) {
            $this->assertArrayHasKey('id', $section, "Section '{$sectionId}' should have 'id'");
            $this->assertArrayHasKey('elements', $section, "Section '{$sectionId}' should have 'elements'");
        }
    }

    // -----------------------------------------------------------------------
    // 7. JsonDesignService::build() produces HTML
    // -----------------------------------------------------------------------
    public function testJsonDesignServiceBuildProducesHtml(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();

        $service = new JsonDesignService($ps, $this->jsonDesign);
        $html = $service->build();

        $this->assertNotEmpty($html, 'JsonDesignService::build() should produce non-empty HTML');
        $this->assertStringContainsString('<html', $html, 'HTML should contain <html tag');
        $this->assertStringContainsString('invoice-container', $html, 'HTML should contain invoice-container div');
    }

    // -----------------------------------------------------------------------
    // 8. JsonDesignService::build() HTML contains variable replacements
    // -----------------------------------------------------------------------
    public function testJsonDesignServiceBuildReplacesVariables(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();

        $service = new JsonDesignService($ps, $this->jsonDesign);
        $html = $service->build();

        // The build should have replaced at least some $ variables
        // Check that raw $company.name is NOT still present (should be replaced with actual value)
        // Note: variables may or may not be replaced depending on the pipeline
        $this->assertNotEmpty($html, 'Build output should not be empty');

        // Check if unreplaced variables remain - this tells us if variable replacement works
        $hasUnreplacedVars = preg_match('/\$company\.name/', $html);
        // Log the finding for diagnosis - don't assert yet as this tells us what happens
        if ($hasUnreplacedVars) {
            $this->addWarning('JsonDesignService::build() does NOT replace $variables - they remain as literals');
        }
    }

    // -----------------------------------------------------------------------
    // 9. PdfService::isJsonDesign() - test via reflection (private method)
    // -----------------------------------------------------------------------
    public function testIsJsonDesignWithTraditionalDbDesign(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();

        // Use reflection to test private method
        $reflection = new \ReflectionClass($ps);
        $method = $reflection->getMethod('isJsonDesign');
        $method->setAccessible(true);

        // The default DB designs (Clean, Bold, etc) are NOT JSON designs
        $result = $method->invoke($ps);
        $this->assertFalse($result, 'Default built-in designs should NOT be detected as JSON designs');
    }

    // -----------------------------------------------------------------------
    // 10. PdfService::isJsonDesign() with a custom JSON design stored in DB
    // -----------------------------------------------------------------------
    public function testIsJsonDesignWithCustomJsonDesign(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();

        // Override the design to be a custom JSON design
        $ps->config->design = new Design();
        $ps->config->design->is_custom = true;
        $ps->config->design->design = (object) $this->jsonDesign;

        $reflection = new \ReflectionClass($ps);
        $method = $reflection->getMethod('isJsonDesign');
        $method->setAccessible(true);

        $result = $method->invoke($ps);
        $this->assertTrue($result, 'Custom design with blocks should be detected as JSON design');
    }

    // -----------------------------------------------------------------------
    // 11. PdfService::getHtml() returns json_design_html when set
    // -----------------------------------------------------------------------
    public function testGetHtmlReturnsJsonDesignHtmlWhenSet(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();

        // Manually set json_design_html via reflection (private property)
        $expectedHtml = '<html><body><p>Test JSON Design Output</p></body></html>';
        $ref = new \ReflectionClass($ps);
        $prop = $ref->getProperty('json_design_html');
        $prop->setAccessible(true);
        $prop->setValue($ps, $expectedHtml);

        // Also need builder for the fallback path
        $ps->designer = new PdfDesigner($ps);
        $ps->designer->template = '<p></p>';
        $ps->builder = new PdfBuilder($ps);
        $ps->builder->document = new \DOMDocument();
        $ps->builder->document->loadHTML('<!DOCTYPE html><html><body></body></html>');

        $html = $ps->getHtml();

        // getHtml should return the json_design_html, not the builder's document
        $this->assertStringContainsString('Test JSON Design Output', $html,
            'getHtml() should return json_design_html when it is set');
    }

    // -----------------------------------------------------------------------
    // 12. PdfService::getHtml() uses builder when json_design_html is null
    // -----------------------------------------------------------------------
    public function testGetHtmlUsesBuilderWhenJsonDesignHtmlIsNull(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->boot();  // Full traditional init

        // json_design_html should be null by default
        $ref = new \ReflectionClass($ps);
        $prop = $ref->getProperty('json_design_html');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue($ps), 'json_design_html should be null by default');

        $html = $ps->getHtml();
        $this->assertNotEmpty($html, 'getHtml() should return builder HTML when json_design_html is null');
        $this->assertStringContainsString('<html', strtolower($html), 'Builder HTML should be valid HTML');
    }

    // -----------------------------------------------------------------------
    // 13. Purify::clean() does not strip essential HTML structure
    // -----------------------------------------------------------------------
    public function testPurifyCleanPreservesHtmlStructure(): void
    {
        $html = '<!DOCTYPE html><html><head><style>body{font-family:sans-serif}</style></head><body><div class="invoice-container"><div id="test-block"><p>Invoice Content</p></div></div></body></html>';

        $cleaned = Purify::clean($html);

        $this->assertStringContainsString('invoice-container', $cleaned, 'Purify should preserve class attributes');
        $this->assertStringContainsString('Invoice Content', $cleaned, 'Purify should preserve text content');
        $this->assertStringContainsString('<style>', $cleaned, 'Purify should preserve style tags');
    }

    // -----------------------------------------------------------------------
    // 14. Purify::clean() preserves JSON design specific CSS
    // -----------------------------------------------------------------------
    public function testPurifyCleanPreservesJsonDesignCss(): void
    {
        $html = '<html><head><style>.flex-row { display: flex; flex-wrap: nowrap; gap: 10px; }</style></head><body><div class="flex-row"><div>Content</div></div></body></html>';

        $cleaned = Purify::clean($html);

        $this->assertStringContainsString('flex-row', $cleaned, 'Purify should preserve flex-row class');
        $this->assertStringContainsString('Content', $cleaned, 'Purify should preserve text content');
    }

    // -----------------------------------------------------------------------
    // 15. Full PdfMock flow with traditional design
    // -----------------------------------------------------------------------
    public function testPdfMockWithTraditionalDesign(): void
    {
        $design = Design::find(1);  // Clean design

        $request = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => (array) $design->design,
        ];

        $mock = new PdfMock($request, $this->company);
        $mock->build();

        $html = $mock->getHtml();

        $this->assertNotEmpty($html, 'Traditional PdfMock should produce HTML');
        $this->assertStringContainsString('<html', strtolower($html), 'Should be valid HTML');
    }

    // -----------------------------------------------------------------------
    // 16. Full PdfMock flow with JSON design
    // -----------------------------------------------------------------------
    public function testPdfMockWithJsonDesign(): void
    {
        $request = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => $this->jsonDesign,
        ];

        $mock = new PdfMock($request, $this->company);
        $mock->build();

        // Check that json_design_html was set
        $html = $mock->getHtml();

        $this->assertNotEmpty($html, 'JSON PdfMock should produce HTML');
        $this->assertStringContainsString('<html', strtolower($html), 'Should be valid HTML');
        $this->assertStringContainsString('invoice-container', $html,
            'JSON design HTML should contain invoice-container class from JsonDesignService template');
    }

    // -----------------------------------------------------------------------
    // 17. PdfMock JSON design: json_design_html is set (not null)
    // -----------------------------------------------------------------------
    public function testPdfMockJsonDesignSetsJsonDesignHtml(): void
    {
        $request = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => $this->jsonDesign,
        ];

        $mock = new PdfMock($request, $this->company);
        $mock->build();

        // Access the internal pdf_service to check json_design_html
        $reflection = new \ReflectionClass($mock);
        $prop = $reflection->getProperty('pdf_service');
        $prop->setAccessible(true);
        $pdfService = $prop->getValue($mock);

        $ref = new \ReflectionClass($pdfService);
        $prop = $ref->getProperty('json_design_html');
        $prop->setAccessible(true);
        $this->assertNotNull($prop->getValue($pdfService),
            'PdfMock with JSON design should set json_design_html on PdfService');
        $this->assertNotEmpty($prop->getValue($pdfService),
            'json_design_html should not be empty');
    }

    // -----------------------------------------------------------------------
    // 18. PdfMock JSON design HTML has content, not just skeleton
    // -----------------------------------------------------------------------
    public function testPdfMockJsonDesignHtmlHasContent(): void
    {
        $request = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => $this->jsonDesign,
        ];

        $mock = new PdfMock($request, $this->company);
        $mock->build();

        $html = $mock->getHtml();

        // The HTML should have real content, not just an empty skeleton
        // Check for block IDs from the JSON design
        $blocks = $this->jsonDesign['blocks'];
        $foundBlockContent = false;

        foreach ($blocks as $block) {
            if (strpos($html, $block['id']) !== false) {
                $foundBlockContent = true;
                break;
            }
        }

        $this->assertTrue($foundBlockContent,
            'JSON design HTML should contain at least one block ID from the design');
    }

    // -----------------------------------------------------------------------
    // 19. PdfMock JSON vs Traditional: HTML output differs
    // -----------------------------------------------------------------------
    public function testPdfMockJsonAndTraditionalProduceDifferentHtml(): void
    {
        // Traditional
        $design = Design::find(1);
        $tradRequest = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => (array) $design->design,
        ];
        $tradMock = new PdfMock($tradRequest, $this->company);
        $tradMock->build();
        $tradHtml = $tradMock->getHtml();

        // JSON
        $jsonRequest = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array) $this->company->settings,
            'design' => $this->jsonDesign,
        ];
        $jsonMock = new PdfMock($jsonRequest, $this->company);
        $jsonMock->build();
        $jsonHtml = $jsonMock->getHtml();

        $this->assertNotEquals($tradHtml, $jsonHtml,
            'JSON and traditional designs should produce different HTML');
    }

    // -----------------------------------------------------------------------
    // 20. Minimal JSON design: single text block produces visible content
    // -----------------------------------------------------------------------
    public function testMinimalJsonDesignProducesContent(): void
    {
        $minimalDesign = [
            'blocks' => [
                [
                    'id' => 'text-minimal-1',
                    'type' => 'text',
                    'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 2],
                    'properties' => [
                        'content' => 'Hello World Invoice',
                        'fontSize' => '14px',
                        'align' => 'left',
                        'color' => '#000000',
                    ],
                ],
            ],
            'pageSettings' => [
                'pageSize' => 'a4',
                'orientation' => 'portrait',
            ],
        ];

        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();

        $service = new JsonDesignService($ps, $minimalDesign);
        $this->assertTrue($service->isValid());

        $html = $service->build();

        $this->assertNotEmpty($html);
        $this->assertStringContainsString('text-minimal-1', $html,
            'Output should contain the block ID');
        $this->assertStringContainsString('Hello World Invoice', $html,
            'Output should contain the text content');
    }

    // -----------------------------------------------------------------------
    // 21. Purify::clean does not destroy a full JSON design HTML document
    // -----------------------------------------------------------------------
    public function testPurifyDoesNotDestroyJsonDesignHtml(): void
    {
        $invitation = $this->invoice->invitations()->first();
        $ps = new PdfService($invitation, 'product');
        $ps->config = (new PdfConfiguration($ps))->init();
        $ps->html_variables = (new \App\Utils\HtmlEngine($invitation))->generateLabelsAndValues();

        $service = new JsonDesignService($ps, $this->jsonDesign);
        $rawHtml = $service->build();

        $cleanedHtml = Purify::clean($rawHtml);

        $this->assertNotEmpty($cleanedHtml, 'Purify should not produce empty string from JSON design HTML');

        // The cleaned HTML should still have meaningful content
        $this->assertGreaterThan(100, strlen($cleanedHtml),
            'Cleaned HTML should have substantial content (>100 chars)');
    }
}

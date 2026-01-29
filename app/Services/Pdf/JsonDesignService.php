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

namespace App\Services\Pdf;

/**
 * Service for handling JSON-based visual designer templates
 *
 * This service acts as a bridge between the JSON visual designer output
 * and PdfBuilder, maintaining complete abstraction without modifying
 * PdfBuilder's core methods.
 *
 * Flow:
 * 1. Receives JSON design from visual designer
 * 2. Converts JSON blocks to PdfBuilder sections via adapter
 * 3. Generates base HTML template
 * 4. Injects sections into PdfBuilder
 * 5. Returns compiled HTML via PdfBuilder pipeline
 *
 * @see JsonToSectionsAdapter
 * @see PdfBuilder
 */
class JsonDesignService
{
    private PdfService $pdfService;
    private array $jsonDesign;
    private JsonToSectionsAdapter $adapter;

    /**
     * @param PdfService $pdfService
     * @param array $jsonDesign Complete JSON design with blocks and pageSettings
     */
    public function __construct(PdfService $pdfService, array $jsonDesign)
    {
        $this->pdfService = $pdfService;
        $this->jsonDesign = $jsonDesign;
        $this->adapter = new JsonToSectionsAdapter($jsonDesign, $pdfService);
    }

    /**
     * Build PDF using JSON design
     *
     * @return string Compiled HTML
     */
    public function build(): string
    {
        // Ensure PdfService is initialized
        if (!isset($this->pdfService->designer)) {
            $this->pdfService->init();
        }

        // Convert JSON blocks to PdfBuilder sections
        $sections = $this->adapter->toSections();

        // Generate base template for JSON design
        $baseTemplate = $this->generateBaseTemplate();

        // Set the template on the designer
        $this->pdfService->designer->template = $baseTemplate;

        // Create PdfBuilder instance
        $builder = new PdfBuilder($this->pdfService);

        // Override the document type to use custom sections
        // This prevents buildSections() from generating default sections
        $this->pdfService->document_type = 'json_design';

        // Populate table bodies before injecting sections
        $sections = $this->populateTableBodies($sections, $builder);

        // Inject our sections before build
        $builder->setSections($sections);

        // Now build normally - buildSections() will be a no-op for 'json_design' type
        // We need to manually run the pipeline steps since build() is private
        // Actually, let's just use the existing build process
        $builder->build();

        // Get the compiled HTML
        return $builder->getCompiledHTML();
    }

    /**
     * Populate table bodies in sections using PdfBuilder
     *
     * Finds table elements in sections and populates their tbody
     * using PdfBuilder's buildTableBody() method.
     *
     * @param array $sections
     * @param PdfBuilder $builder
     * @return array
     */
    private function populateTableBodies(array $sections, PdfBuilder $builder): array
    {
        foreach ($sections as $sectionId => &$section) {
            if (isset($section['elements'])) {
                $section['elements'] = $this->populateTableBodyElements($section['elements'], $builder);
            }
        }

        return $sections;
    }

    /**
     * Recursively populate table body elements
     *
     * @param array $elements
     * @param PdfBuilder $builder
     * @return array
     */
    private function populateTableBodyElements(array $elements, PdfBuilder $builder): array
    {
        foreach ($elements as &$element) {
            // Check if this is a table element
            if (isset($element['element']) && $element['element'] === 'table') {
                // Check if it has a data-table-type attribute
                $tableType = $element['properties']['data-table-type'] ?? null;

                if ($tableType && isset($element['elements'])) {
                    // Find tbody in table elements
                    foreach ($element['elements'] as &$tableChild) {
                        if (isset($tableChild['element']) && $tableChild['element'] === 'tbody') {
                            // Populate tbody with rows from PdfBuilder
                            $tableChild['elements'] = $builder->buildTableBody('$' . $tableType);
                        }
                    }
                }
            }

            // Recurse into nested elements
            if (isset($element['elements'])) {
                $element['elements'] = $this->populateTableBodyElements($element['elements'], $builder);
            }
        }

        return $elements;
    }

    /**
     * Generate base HTML template structure for JSON designs
     *
     * Creates a minimal HTML skeleton with placeholders for each
     * JSON block, respecting row grouping for blocks at the same Y position.
     *
     * @return string
     */
    private function generateBaseTemplate(): string
    {
        $blocks = $this->jsonDesign['blocks'] ?? [];
        $pageSettings = $this->jsonDesign['pageSettings'] ?? [];

        // Build page CSS from settings
        $pageCSS = $this->buildPageCSS($pageSettings);

        // Get blocks grouped by row for layout
        $rows = $this->adapter->getRowGroupedBlocks();

        // Build container divs with flex row wrapping for multi-block rows
        $blockContainers = '';
        foreach ($rows as $rowBlocks) {
            if (count($rowBlocks) === 1) {
                // Single block - render normally
                $block = $rowBlocks[0];
                $blockContainers .= "<div id=\"{$block['id']}\"></div>\n";
            } else {
                // Multiple blocks on same row - wrap in flex container
                $blockContainers .= "<div class=\"flex-row\">\n";
                foreach ($rowBlocks as $block) {
                    $widthPercent = ($block['gridPosition']['w'] / 12) * 100;
                    $blockContainers .= "  <div class=\"flex-col\" style=\"width: {$widthPercent}%;\">\n";
                    $blockContainers .= "    <div id=\"{$block['id']}\"></div>\n";
                    $blockContainers .= "  </div>\n";
                }
                $blockContainers .= "</div>\n";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
        {$pageCSS}
    </style>
</head>
<body>
    <div class="invoice-container">
        {$blockContainers}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build CSS from page settings
     *
     * @param array $pageSettings
     * @return string
     */
    private function buildPageCSS(array $pageSettings): string
    {
        $pageSize = $this->getPageSizeCSS($pageSettings);
        $pageMargins = $this->getPageMarginsCSS($pageSettings);
        $fontFamily = $pageSettings['fontFamily'] ?? 'Inter, sans-serif';
        $fontSize = $pageSettings['fontSize'] ?? '12px';
        $textColor = $pageSettings['textColor'] ?? '#374151';
        $lineHeight = $pageSettings['lineHeight'] ?? '1.5';
        $backgroundColor = $pageSettings['backgroundColor'] ?? '#ffffff';

        return <<<CSS
        @page {
            size: {$pageSize};
            margin: {$pageMargins};
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: {$fontFamily};
            font-size: {$fontSize};
            color: {$textColor};
            line-height: {$lineHeight};
            background-color: {$backgroundColor};
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .invoice-container {
            width: 794px;
            background: {$backgroundColor};
            margin: 0 auto;
            padding: 30px;
        }
        .flex-row {
            display: flex;
            flex-wrap: nowrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .flex-col {
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .invoice-container {
                margin: 0;
            }
        }
CSS;
    }

    /**
     * Get CSS page size string based on settings
     *
     * @param array $pageSettings
     * @return string
     */
    private function getPageSizeCSS(array $pageSettings): string
    {
        $pageSize = $pageSettings['pageSize'] ?? 'a4';
        $orientation = $pageSettings['orientation'] ?? 'portrait';

        if ($pageSize === 'custom') {
            $width = $pageSettings['customWidth'] ?? '210mm';
            $height = $pageSettings['customHeight'] ?? '297mm';
            return "{$width} {$height}";
        }

        $sizes = [
            'a4' => ['width' => 210, 'height' => 297],
            'letter' => ['width' => 216, 'height' => 279],
            'legal' => ['width' => 216, 'height' => 356],
            'a3' => ['width' => 297, 'height' => 420],
            'a5' => ['width' => 148, 'height' => 210],
        ];

        $size = $sizes[$pageSize] ?? $sizes['a4'];
        $width = $orientation === 'landscape' ? $size['height'] : $size['width'];
        $height = $orientation === 'landscape' ? $size['width'] : $size['height'];

        return "{$width}mm {$height}mm";
    }

    /**
     * Get CSS page margins string based on settings
     *
     * @param array $pageSettings
     * @return string
     */
    private function getPageMarginsCSS(array $pageSettings): string
    {
        $top = $pageSettings['marginTop'] ?? '10mm';
        $right = $pageSettings['marginRight'] ?? '10mm';
        $bottom = $pageSettings['marginBottom'] ?? '10mm';
        $left = $pageSettings['marginLeft'] ?? '10mm';

        return "{$top} {$right} {$bottom} {$left}";
    }

    /**
     * Get page settings from JSON design
     *
     * @return array
     */
    public function getPageSettings(): array
    {
        return $this->jsonDesign['pageSettings'] ?? [];
    }

    /**
     * Get blocks from JSON design
     *
     * @return array
     */
    public function getBlocks(): array
    {
        return $this->jsonDesign['blocks'] ?? [];
    }

    /**
     * Validate JSON design structure
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!isset($this->jsonDesign['blocks']) || !is_array($this->jsonDesign['blocks'])) {
            return false;
        }

        // Basic validation of block structure
        foreach ($this->jsonDesign['blocks'] as $block) {
            if (!isset($block['id']) || !isset($block['type']) || !isset($block['gridPosition'])) {
                return false;
            }
        }

        return true;
    }
}

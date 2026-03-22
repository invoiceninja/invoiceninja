<?php

namespace Tests\Feature\Design;

/**
 * Converts Invoice Builder JSON design to HTML/CSS layout
 *
 * This class takes the JSON blocks from the visual builder and generates
 * clean HTML with inline CSS positioning. Table blocks are wrapped in
 * <ninja></ninja> tags containing Twig loop syntax for the backend to process.
 *
 * Variable Syntax (in JSON):
 * - Line item fields: "item.product_key", "item.quantity", "item.cost", etc.
 * - These are converted to Twig: {{ item.product_key }}
 *
 * Output Example (table):
 * <ninja>
 *   {% for item in invoice.line_items %}
 *   <tr>
 *     <td>{{ item.product_key }}</td>
 *     <td>{{ item.quantity }}</td>
 *   </tr>
 *   {% endfor %}
 * </ninja>
 *
 * @example
 * $renderer = new InvoiceDesignRenderer();
 * $html = $renderer->render($designBlocks);
 */
class InvoiceDesignRenderer
{
    /**
     * Grid system constants (must match frontend exactly)
     */
    const GRID_COLS = 12;
    const ROW_HEIGHT = 60;        // pixels
    const CANVAS_WIDTH = 794;     // pixels (210mm at 96dpi)
    const MARGIN_H = 10;          // horizontal margin between columns
    const MARGIN_V = 10;          // vertical margin between rows
    const PADDING_H = 30;         // container horizontal padding
    const PADDING_V = 30;         // container vertical padding

    /**
     * Page sizes in mm (portrait dimensions)
     */
    const PAGE_SIZES = [
        'a4' => ['width' => 210, 'height' => 297],
        'letter' => ['width' => 216, 'height' => 279],
        'legal' => ['width' => 216, 'height' => 356],
        'a3' => ['width' => 297, 'height' => 420],
        'a5' => ['width' => 148, 'height' => 210],
    ];

    /**
     * Default page settings
     */
    const DEFAULT_PAGE_SETTINGS = [
        'pageSize' => 'a4',
        'orientation' => 'portrait',
        'marginTop' => '10mm',
        'marginRight' => '10mm',
        'marginBottom' => '10mm',
        'marginLeft' => '10mm',
        'backgroundColor' => '#ffffff',
        'fontFamily' => 'Inter, sans-serif',
        'fontSize' => '12px',
        'textColor' => '#374151',
        'lineHeight' => '1.5',
    ];

    /**
     * Current page settings
     */
    private array $pageSettings;

    /**
     * Constructor
     */
    public function __construct(array $pageSettings = [])
    {
        $this->pageSettings = array_merge(self::DEFAULT_PAGE_SETTINGS, $pageSettings);
    }

    /**
     * Render complete HTML document from blocks using flow-based layout
     * This ensures content can grow and push other elements down naturally
     *
     * @param array $blocks Array of block objects from frontend
     * @param array|null $pageSettings Optional page settings override
     * @return string Complete HTML document
     */
    public function render(array $blocks, ?array $pageSettings = null): string
    {
        // Merge page settings if provided
        if ($pageSettings !== null) {
            $this->pageSettings = array_merge(self::DEFAULT_PAGE_SETTINGS, $pageSettings);
        }
        // Sort blocks by Y position, then by X position
        usort($blocks, function($a, $b) {
            if ($a['gridPosition']['y'] !== $b['gridPosition']['y']) {
                return $a['gridPosition']['y'] - $b['gridPosition']['y'];
            }
            return $a['gridPosition']['x'] - $b['gridPosition']['x'];
        });

        // Group blocks into rows
        $rows = $this->groupBlocksIntoRows($blocks);
        $rowsHTML = '';
        foreach ($rows as $row) {
            $rowsHTML .= $this->renderRow($row);
        }

        return $this->generateDocument($rowsHTML);
    }

    /**
     * Group blocks into rows based on similar Y positions
     */
    private function groupBlocksIntoRows(array $blocks): array
    {
        $rows = [];
        $currentRow = [];
        $currentY = -1;

        foreach ($blocks as $block) {
            $blockY = $block['gridPosition']['y'];
            
            if ($currentY === -1 || abs($blockY - $currentY) >= 1) {
                if (!empty($currentRow)) {
                    $rows[] = $currentRow;
                }
                $currentRow = [$block];
                $currentY = $blockY;
            } else {
                $currentRow[] = $block;
            }
        }

        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * Render a row of blocks
     */
    private function renderRow(array $blocks): string
    {
        $blocksHTML = '';
        foreach ($blocks as $block) {
            $blocksHTML .= $this->renderBlock($block);
        }

        $rowClass = 'row';
        $rowStyle = '';

        if (count($blocks) > 1) {
            // Multiple blocks - use flex with gap
            $rowClass = 'row flex-row';
            $rowStyle = 'gap: ' . self::MARGIN_H . 'px;';
        } elseif (count($blocks) === 1) {
            // Single block - check if it needs alignment
            $block = $blocks[0];
            $xPos = $block['gridPosition']['x'];
            $width = $block['gridPosition']['w'];

            if ($xPos > 0) {
                // Block is not at left edge - use flex for positioning
                $rowClass = 'row flex-row';

                if ($xPos + $width >= self::GRID_COLS) {
                    // Block is at right edge
                    $rowStyle = 'justify-content: flex-end;';
                } elseif ($xPos >= (self::GRID_COLS - $width) / 2 - 1 && $xPos <= (self::GRID_COLS - $width) / 2 + 1) {
                    // Block is roughly centered
                    $rowStyle = 'justify-content: center;';
                } else {
                    // Block has specific left offset - use padding
                    $leftPercent = ($xPos / self::GRID_COLS) * 100;
                    $rowStyle = "padding-left: {$leftPercent}%;";
                }
            }
        }
        
        return "<div class=\"{$rowClass}\" style=\"{$rowStyle}\">{$blocksHTML}</div>\n";
    }

    /**
     * Get CSS page size string based on settings
     */
    private function getPageSizeCSS(): string
    {
        $pageSize = $this->pageSettings['pageSize'] ?? 'a4';
        $orientation = $this->pageSettings['orientation'] ?? 'portrait';

        if ($pageSize === 'custom') {
            $width = $this->pageSettings['customWidth'] ?? '210mm';
            $height = $this->pageSettings['customHeight'] ?? '297mm';
            return "{$width} {$height}";
        }

        $size = self::PAGE_SIZES[$pageSize] ?? self::PAGE_SIZES['a4'];
        $width = $orientation === 'landscape' ? $size['height'] : $size['width'];
        $height = $orientation === 'landscape' ? $size['width'] : $size['height'];
        
        return "{$width}mm {$height}mm";
    }

    /**
     * Get CSS page margins string based on settings
     */
    private function getPageMarginsCSS(): string
    {
        $top = $this->pageSettings['marginTop'] ?? '10mm';
        $right = $this->pageSettings['marginRight'] ?? '10mm';
        $bottom = $this->pageSettings['marginBottom'] ?? '10mm';
        $left = $this->pageSettings['marginLeft'] ?? '10mm';
        
        return "{$top} {$right} {$bottom} {$left}";
    }

    /**
     * Generate complete HTML document structure with flow-based CSS
     */
    private function generateDocument(string $content): string
    {
        $padding = self::PADDING_V . 'px ' . self::PADDING_H . 'px';
        $marginBottom = self::MARGIN_V . 'px';
        
        // Page settings
        $pageSize = $this->getPageSizeCSS();
        $pageMargins = $this->getPageMarginsCSS();
        $fontFamily = $this->pageSettings['fontFamily'] ?? "Inter, sans-serif";
        $fontSize = $this->pageSettings['fontSize'] ?? '12px';
        $textColor = $this->pageSettings['textColor'] ?? '#374151';
        $lineHeight = $this->pageSettings['lineHeight'] ?? '1.5';
        $backgroundColor = $this->pageSettings['backgroundColor'] ?? '#ffffff';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice</title>
    <style>
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
            padding: {$padding};
        }
        .row {
            display: block;
            margin-bottom: {$marginBottom};
            width: 100%;
            clear: both;
        }
        .row::after {
            content: "";
            display: table;
            clear: both;
        }
        .row.flex-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-start;
        }
        .block {
            box-sizing: border-box;
            overflow: visible;
        }
        .block.full-width {
            width: 100% !important;
            display: block;
            float: none;
            clear: both;
        }
        .row.flex-row .block {
            flex-shrink: 0;
            flex-grow: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table tr {
            page-break-inside: avoid;
        }
        .block p, .block div {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        /* Page break utilities */
        .page-break-before {
            page-break-before: always;
            break-before: page;
        }
        .page-break-after {
            page-break-after: always;
            break-after: page;
        }
        .page-break-avoid {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        /* Repeat on every page (for headers/footers) */
        .repeat-header {
            display: table-header-group;
        }
        .repeat-footer {
            display: table-footer-group;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .invoice-container {
                margin: 0;
            }
            .block {
                break-inside: avoid;
                box-shadow: none !important;
            }
            .page-break-before {
                page-break-before: always !important;
                break-before: page !important;
            }
            .page-break-after {
                page-break-after: always !important;
                break-after: page !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        {$content}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Generate CSS styles from block.properties.styles
     */
    private function generateBlockStyles(array $block): array
    {
        $props = $block['properties'] ?? [];
        $styles = $props['styles'] ?? [];
        $cssStyles = [];

        // Background
        if (!empty($styles['backgroundColor']) && $styles['backgroundColor'] !== 'transparent') {
            $cssStyles[] = "background-color: {$styles['backgroundColor']}";
        }
        if (!empty($styles['opacity'])) {
            $cssStyles[] = "opacity: {$styles['opacity']}";
        }

        // Borders
        if (!empty($styles['borderTopStyle']) && $styles['borderTopStyle'] !== 'none') {
            $width = $styles['borderTopWidth'] ?? '1px';
            $color = $styles['borderTopColor'] ?? '#000000';
            $cssStyles[] = "border-top: {$width} {$styles['borderTopStyle']} {$color}";
        }
        if (!empty($styles['borderBottomStyle']) && $styles['borderBottomStyle'] !== 'none') {
            $width = $styles['borderBottomWidth'] ?? '1px';
            $color = $styles['borderBottomColor'] ?? '#000000';
            $cssStyles[] = "border-bottom: {$width} {$styles['borderBottomStyle']} {$color}";
        }
        if (!empty($styles['borderLeftStyle']) && $styles['borderLeftStyle'] !== 'none') {
            $width = $styles['borderLeftWidth'] ?? '1px';
            $color = $styles['borderLeftColor'] ?? '#000000';
            $cssStyles[] = "border-left: {$width} {$styles['borderLeftStyle']} {$color}";
        }
        if (!empty($styles['borderRightStyle']) && $styles['borderRightStyle'] !== 'none') {
            $width = $styles['borderRightWidth'] ?? '1px';
            $color = $styles['borderRightColor'] ?? '#000000';
            $cssStyles[] = "border-right: {$width} {$styles['borderRightStyle']} {$color}";
        }
        if (!empty($styles['borderRadius'])) {
            $cssStyles[] = "border-radius: {$styles['borderRadius']}";
        }

        // Spacing
        if (!empty($styles['padding'])) {
            $cssStyles[] = "padding: {$styles['padding']}";
        }
        if (!empty($styles['margin'])) {
            $cssStyles[] = "margin: {$styles['margin']}";
        }

        // Page break behavior
        if (!empty($styles['pageBreak']) && $styles['pageBreak'] !== 'auto') {
            switch ($styles['pageBreak']) {
                case 'before':
                    $cssStyles[] = 'page-break-before: always';
                    break;
                case 'after':
                    $cssStyles[] = 'page-break-after: always';
                    break;
                case 'avoid':
                    $cssStyles[] = 'page-break-inside: avoid';
                    break;
                case 'always':
                    $cssStyles[] = 'page-break-before: always';
                    $cssStyles[] = 'page-break-after: always';
                    break;
            }
        }

        return $cssStyles;
    }

    /**
     * Render a single block with flow-based layout
     * Each block has a unique ID for CSS targeting
     */
    private function renderBlock(array $block): string
    {
        $gridPos = $block['gridPosition'];
        $blockId = $block['id'] ?? $this->generateBlockId($block['type']);
        $blockType = $block['type'];
        
        $content = $this->renderBlockContent($block, $blockId);
        
        // Calculate width as percentage of 12 columns
        $widthPercent = ($gridPos['w'] / self::GRID_COLS) * 100;
        $isFullWidth = $gridPos['w'] === self::GRID_COLS;
        
        // Expandable blocks (tables, totals) should not have min-height constraints
        $isExpandable = in_array($blockType, ['table', 'total', 'invoice-details']);
        
        // CSS classes for targeting
        $classes = ['block', "block-{$blockType}"];
        if ($isFullWidth) {
            $classes[] = 'full-width';
        }
        
        $styles = [];
        if (!$isFullWidth) {
            $styles[] = "width: {$widthPercent}%";
        }
        if (!$isExpandable) {
            $minHeight = $gridPos['h'] * self::ROW_HEIGHT;
            $styles[] = "min-height: {$minHeight}px";
        }
        
        // Add custom block styles
        $blockStyles = $this->generateBlockStyles($block);
        $styles = array_merge($styles, $blockStyles);
        
        $classAttr = implode(' ', $classes);
        $styleAttr = !empty($styles) ? ' style="' . implode('; ', $styles) . ';"' : '';

        return "<div id=\"{$blockId}\" class=\"{$classAttr}\"{$styleAttr}>{$content}</div>\n";
    }

    /**
     * Generate a unique block ID if not provided
     */
    private function generateBlockId(string $type): string
    {
        static $counter = 0;
        $counter++;
        return "{$type}-{$counter}";
    }

    /**
     * Render block content based on type
     */
    private function renderBlockContent(array $block, string $blockId): string
    {
        $type = $block['type'];
        $props = $block['properties'];

        return match($type) {
            'text' => $this->renderText($props, $blockId),
            'logo', 'image' => $this->renderImage($props, $type, $blockId),
            'company-info' => $this->renderCompanyInfo($props, $blockId),
            'client-info' => $this->renderClientInfo($props, $blockId),
            'invoice-details' => $this->renderInvoiceDetails($props, $blockId),
            'table' => $this->renderTable($props, $blockId),
            'total' => $this->renderTotal($props, $blockId),
            'divider' => $this->renderDivider($props, $blockId),
            'spacer' => $this->renderSpacer($props, $blockId),
            'qrcode' => $this->renderQRCode($props, $blockId),
            'signature' => $this->renderSignature($props, $blockId),
            default => "<div>Unknown block: {$type}</div>"
        };
    }

    /**
     * TEXT BLOCK
     * Renders multi-line text using div elements instead of br tags
     */
    private function renderText(array $props, string $blockId): string
    {
        $content = $props['content'] ?? '';
        $lines = explode("\n", $content);
        
        $containerStyle = $this->buildStyle([
            'font-size' => $props['fontSize'] ?? '14px',
            'font-weight' => $props['fontWeight'] ?? 'normal',
            'font-style' => $props['fontStyle'] ?? 'normal',
            'color' => $props['color'] ?? '#000000',
            'text-align' => $props['align'] ?? 'left',
            'line-height' => $props['lineHeight'] ?? '1.5',
        ]);

        $html = "<div class=\"text-content\" style=\"{$containerStyle}\">";
        
        foreach ($lines as $index => $line) {
            $lineId = "{$blockId}-line-{$index}";
            $escapedLine = $this->escape(trim($line));
            
            // Use span for inline elements, div for block-level lines
            if (empty(trim($line))) {
                $html .= "<div class=\"text-line text-line-empty\" id=\"{$lineId}\">&nbsp;</div>";
            } else {
                $html .= "<div class=\"text-line\" id=\"{$lineId}\">{$escapedLine}</div>";
            }
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * IMAGE/LOGO BLOCK
     * Supports:
     * - Base64 encoded images (data:image/...)
     * - External URLs (https://...)
     * - Variables for backend replacement ($company.logo)
     */
    private function renderImage(array $props, string $type, string $blockId): string
    {
        $source = $props['source'] ?? '';
        $imageId = "{$blockId}-img";

        if (empty($source)) {
            $placeholder = $type === 'logo' ? 'Company Logo' : 'Image';
            return sprintf(
                '<div id="%s" class="image-placeholder" style="%s">%s</div>',
                $imageId,
                $this->buildStyle([
                    'width' => '100%',
                    'height' => '100%',
                    'background' => '#f3f4f6',
                    'display' => 'flex',
                    'align-items' => 'center',
                    'justify-content' => 'center',
                    'color' => '#9ca3af',
                    'font-size' => '12px',
                ]),
                $placeholder
            );
        }

        // Determine the image source format
        $imageSrc = $this->resolveImageSource($source);

        return sprintf(
            '<div class="image-container" style="%s"><img id="%s" class="%s" src="%s" style="%s" alt="%s" /></div>',
            $this->buildStyle([
                'text-align' => $props['align'] ?? 'left',
                'height' => '100%',
                'display' => 'flex',
                'align-items' => 'center',
                'justify-content' => $props['align'] ?? 'left',
            ]),
            $imageId,
            $type === 'logo' ? 'company-logo' : 'block-image',
            $imageSrc,
            $this->buildStyle([
                'max-width' => $props['maxWidth'] ?? '100%',
                'max-height' => $props['maxHeight'] ?? '100%',
                'object-fit' => $props['objectFit'] ?? 'contain',
            ]),
            $this->escape($type)
        );
    }

    /**
     * Resolve image source based on format
     * - Base64: Return as-is (already embedded)
     * - Variable ($company.logo): Return for backend replacement
     * - URL: Escape and return
     */
    private function resolveImageSource(string $source): string
    {
        // Base64 encoded image - return as-is (don't escape)
        if (str_starts_with($source, 'data:image/')) {
            return $source;
        }

        // Variable for backend replacement - return as-is
        if (str_starts_with($source, '$')) {
            return $source;
        }

        // External URL - escape for HTML safety
        return $this->escape($source);
    }

    /**
     * COMPANY INFO BLOCK
     * Renders each field as a separate div for proper layout control
     */
    private function renderCompanyInfo(array $props, string $blockId): string
    {
        $content = $props['content'] ?? '';
        $fieldConfigs = $props['fieldConfigs'] ?? null;
        
        $containerStyle = $this->buildStyle([
            'font-size' => $props['fontSize'] ?? '12px',
            'font-weight' => $props['fontWeight'] ?? 'normal',
            'font-style' => $props['fontStyle'] ?? 'normal',
            'line-height' => $props['lineHeight'] ?? '1.5',
            'text-align' => $props['align'] ?? 'left',
            'color' => $props['color'] ?? '#374151',
        ]);

        $html = "<div class=\"company-info-content\" style=\"{$containerStyle}\">";
        
        if ($fieldConfigs && is_array($fieldConfigs)) {
            // New structured format with fieldConfigs
            foreach ($fieldConfigs as $index => $config) {
                $fieldId = "{$blockId}-field-{$index}";
                $prefix = $this->escape($config['prefix'] ?? '');
                $variable = $config['variable'] ?? '';
                $suffix = $this->escape($config['suffix'] ?? '');
                
                $html .= "<div class=\"info-field\" id=\"{$fieldId}\">";
                if (!empty($prefix)) {
                    $html .= "<span class=\"field-prefix\">{$prefix}</span>";
                }
                $html .= "<span class=\"field-value\">{$variable}</span>";
                if (!empty($suffix)) {
                    $html .= "<span class=\"field-suffix\">{$suffix}</span>";
                }
                $html .= "</div>";
            }
        } else {
            // Legacy content string - split by lines
            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $fieldId = "{$blockId}-field-{$index}";
                // Don't escape - may contain variables like $company.name
                $html .= "<div class=\"info-field\" id=\"{$fieldId}\">{$line}</div>";
            }
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * CLIENT INFO BLOCK
     * Renders with optional title and each field as a separate div
     */
    private function renderClientInfo(array $props, string $blockId): string
    {
        $content = $props['content'] ?? '';
        $fieldConfigs = $props['fieldConfigs'] ?? null;
        
        $html = '<div class="client-info-wrapper">';

        // Optional title
        if ($props['showTitle'] ?? false) {
            $titleId = "{$blockId}-title";
            $html .= sprintf(
                '<div id="%s" class="client-info-title" style="%s">%s</div>',
                $titleId,
                $this->buildStyle([
                    'font-size' => $props['fontSize'] ?? '12px',
                    'font-weight' => $props['titleFontWeight'] ?? 'bold',
                    'color' => $props['color'] ?? '#374151',
                    'margin-bottom' => '8px',
                ]),
                $this->escape($props['title'] ?? '')
            );
        }

        $containerStyle = $this->buildStyle([
            'font-size' => $props['fontSize'] ?? '12px',
            'font-weight' => $props['fontWeight'] ?? 'normal',
            'font-style' => $props['fontStyle'] ?? 'normal',
            'line-height' => $props['lineHeight'] ?? '1.5',
            'text-align' => $props['align'] ?? 'left',
            'color' => $props['color'] ?? '#374151',
        ]);

        $html .= "<div class=\"client-info-content\" style=\"{$containerStyle}\">";
        
        if ($fieldConfigs && is_array($fieldConfigs)) {
            // New structured format with fieldConfigs
            foreach ($fieldConfigs as $index => $config) {
                $fieldId = "{$blockId}-field-{$index}";
                $prefix = $this->escape($config['prefix'] ?? '');
                $variable = $config['variable'] ?? '';
                $suffix = $this->escape($config['suffix'] ?? '');
                
                $html .= "<div class=\"info-field\" id=\"{$fieldId}\">";
                if (!empty($prefix)) {
                    $html .= "<span class=\"field-prefix\">{$prefix}</span>";
                }
                $html .= "<span class=\"field-value\">{$variable}</span>";
                if (!empty($suffix)) {
                    $html .= "<span class=\"field-suffix\">{$suffix}</span>";
                }
                $html .= "</div>";
            }
        } else {
            // Legacy content string - split by lines
            $lines = explode("\n", $content);
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }
                $fieldId = "{$blockId}-field-{$index}";
                // Don't escape - may contain variables like $client.name
                $html .= "<div class=\"info-field\" id=\"{$fieldId}\">{$line}</div>";
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    /**
     * INVOICE DETAILS BLOCK
     * Renders as a table with label/value pairs (similar to Total block)
     * Supports both new 'items' array format and legacy 'content' string
     */
    private function renderInvoiceDetails(array $props, string $blockId): string
    {
        $align = $props['align'] ?? 'left';
        $fontSize = $props['fontSize'] ?? '12px';
        $lineHeight = $props['lineHeight'] ?? '1.5';
        $color = $props['color'] ?? '#374151';
        $labelColor = $props['labelColor'] ?? '#6B7280';
        $rowSpacing = $props['rowSpacing'] ?? '4px';
        $labelWidth = $props['labelWidth'] ?? 'auto';
        $displayAsGrid = $props['displayAsGrid'] ?? true;

        // Check if we have items array (new format) or content string (legacy)
        $items = $props['items'] ?? null;
        
        $styleContext = [
            'align' => $align,
            'fontSize' => $fontSize,
            'lineHeight' => $lineHeight,
            'color' => $color,
            'labelColor' => $labelColor,
            'rowSpacing' => $rowSpacing,
            'labelWidth' => $labelWidth,
            'blockId' => $blockId,
        ];
        
        if ($items && is_array($items) && $displayAsGrid) {
            return $this->renderInvoiceDetailsTable($items, $styleContext);
        }

        // Legacy format: parse content string
        $content = $props['content'] ?? '';
        
        if ($displayAsGrid && !empty($content)) {
            $parsedItems = $this->parseInvoiceDetailsContent($content);
            return $this->renderInvoiceDetailsTable($parsedItems, $styleContext);
        }

        // Fallback: render each line as a div
        $lines = explode("\n", $content);
        $html = "<div class=\"invoice-details-content\">";
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $lineId = "{$blockId}-line-{$index}";
            $html .= "<div class=\"details-line\" id=\"{$lineId}\" style=\"font-size: {$fontSize}; line-height: {$lineHeight}; color: {$color};\">{$line}</div>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Parse legacy content string into items array
     */
    private function parseInvoiceDetailsContent(string $content): array
    {
        $items = [];
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                $label = trim(substr($line, 0, $colonPos + 1));
                $variable = trim(substr($line, $colonPos + 1));
                $items[] = [
                    'label' => $label,
                    'variable' => $variable,
                    'show' => true,
                ];
            } else {
                $items[] = [
                    'label' => '',
                    'variable' => $line,
                    'show' => true,
                ];
            }
        }
        
        return $items;
    }

    /**
     * Render invoice details as a table with IDs for CSS targeting
     */
    private function renderInvoiceDetailsTable(array $items, array $styles): string
    {
        $blockId = $styles['blockId'] ?? 'invoice-details';
        $tableId = "{$blockId}-table";
        
        $tableAlign = match($styles['align']) {
            'right' => 'margin-left: auto;',
            'center' => 'margin: 0 auto;',
            default => '',
        };

        $html = sprintf(
            '<table id="%s" class="invoice-details-table" style="border-collapse: collapse; %s"><tbody>',
            $tableId,
            $tableAlign
        );

        $rowIndex = 0;
        foreach ($items as $item) {
            if (!($item['show'] ?? true)) {
                continue;
            }

            $label = $item['label'] ?? '';
            $variable = $item['variable'] ?? '';
            $rowId = "{$blockId}-row-{$rowIndex}";

            $html .= "<tr id=\"{$rowId}\" class=\"details-row\">";

            // Label cell
            $html .= sprintf(
                '<td class="details-label" style="%s">%s</td>',
                $this->buildStyle([
                    'font-size' => $styles['fontSize'],
                    'line-height' => $styles['lineHeight'],
                    'color' => $styles['labelColor'],
                    'text-align' => $styles['align'] === 'right' ? 'right' : 'left',
                    'padding-bottom' => $styles['rowSpacing'],
                    'padding-right' => '12px',
                    'white-space' => 'nowrap',
                    'width' => $styles['labelWidth'],
                ]),
                $this->escape($label)
            );

            // Value cell
            $html .= sprintf(
                '<td class="details-value" style="%s">%s</td>',
                $this->buildStyle([
                    'font-size' => $styles['fontSize'],
                    'line-height' => $styles['lineHeight'],
                    'color' => $styles['color'],
                    'text-align' => $styles['align'] === 'right' ? 'right' : 'left',
                    'padding-bottom' => $styles['rowSpacing'],
                ]),
                $variable
            );

            $html .= '</tr>';
            $rowIndex++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * TABLE BLOCK
     * 
     * Column fields use "item.field" notation (e.g., "item.product_key").
     * The entire table body is wrapped in <ninja> tags with Twig loop syntax.
     */
    private function renderTable(array $props, string $blockId): string
    {
        $columns = $props['columns'];
        $tableId = "{$blockId}-table";
        $borderStyle = ($props['showBorders'] ?? true)
            ? "1px solid {$props['borderColor']}"
            : 'none';

        $html = sprintf(
            '<table id="%s" class="line-items-table" style="%s">',
            $tableId,
            $this->buildStyle([
                'width' => '100%',
                'border-collapse' => 'collapse',
                'font-size' => $props['fontSize'],
            ])
        );

        // Header
        $html .= sprintf(
            '<thead><tr id="%s-header" class="table-header" style="%s">',
            $blockId,
            $this->buildStyle([
                'background' => $props['headerBg'],
                'color' => $props['headerColor'],
                'font-weight' => $props['headerFontWeight'],
            ])
        );

        foreach ($columns as $colIndex => $col) {
            $colId = "{$blockId}-col-{$colIndex}";
            $html .= sprintf(
                '<th id="%s" class="table-header-cell" style="%s">%s</th>',
                $colId,
                $this->buildStyle([
                    'padding' => $props['padding'],
                    'text-align' => $col['align'],
                    'width' => $col['width'] ?? 'auto',
                    'border' => $borderStyle,
                ]),
                $this->escape($col['header'])
            );
        }

        $html .= '</tr></thead>';

        // Body - wrapped in <ninja> tags for Twig processing
        $html .= '<tbody class="table-body">';
        $html .= '<ninja>';
        $html .= '{% set invoice = invoices|first %}';
        $html .= '{% for item in invoice.line_items %}';

        // Alternate row background using Twig
        if ($props['alternateRows'] ?? false) {
            $html .= sprintf(
                '<tr class="table-row" style="background: {{ loop.index is even ? \'%s\' : \'%s\' }};">',
                $this->escape($props['alternateRowBg']),
                $this->escape($props['rowBg'])
            );
        } else {
            $html .= sprintf('<tr class="table-row" style="background: %s;">', $props['rowBg']);
        }

        foreach ($columns as $col) {
            $twigVar = '{{ ' . $col['field'] . ' }}';
            
            $html .= sprintf(
                '<td class="table-cell" style="%s">%s</td>',
                $this->buildStyle([
                    'padding' => $props['padding'],
                    'text-align' => $col['align'],
                    'border' => $borderStyle,
                ]),
                $twigVar
            );
        }

        $html .= '</tr>';
        $html .= '{% endfor %}';
        $html .= '</ninja>';
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * TOTAL BLOCK
     */
    private function renderTotal(array $props, string $blockId): string
    {
        $tableId = "{$blockId}-table";
        
        $tableAlign = match($props['align']) {
            'right' => 'margin-left: auto;',
            'center' => 'margin: 0 auto;',
            default => '',
        };

        $gap = $props['labelValueGap'] ?? '20px';
        $labelPadding = $props['labelPadding'] ?? null;
        $valuePadding = $props['valuePadding'] ?? null;
        $valueMinWidth = $props['valueMinWidth'] ?? null;

        $html = sprintf(
            '<table id="%s" class="totals-table" style="border-collapse: collapse; %s"><tbody>',
            $tableId,
            $tableAlign
        );

        $rowIndex = 0;
        foreach ($props['items'] as $item) {
            if (!($item['show'] ?? true)) {
                continue;
            }

            $isTotal = $item['isTotal'] ?? false;
            $isBalance = $item['isBalance'] ?? false;
            $rowId = "{$blockId}-row-{$rowIndex}";
            $rowClass = 'totals-row';
            if ($isTotal) $rowClass .= ' totals-row-total';
            if ($isBalance) $rowClass .= ' totals-row-balance';

            $fontSize = $isTotal ? $props['totalFontSize'] : $props['fontSize'];
            $fontWeight = $isTotal ? $props['totalFontWeight'] : 'normal';

            $valueColor = $isBalance
                ? $props['balanceColor']
                : ($isTotal ? $props['totalColor'] : $props['amountColor']);

            $html .= sprintf(
                '<tr id="%s" class="%s" style="font-size: %s; font-weight: %s;">',
                $rowId,
                $rowClass,
                $fontSize,
                $fontWeight
            );

            // Label cell
            $labelStyles = [
                'color' => $props['labelColor'],
                'text-align' => 'right',
                'white-space' => 'nowrap',
            ];
            if ($labelPadding) {
                $labelStyles['padding'] = $labelPadding;
                $labelStyles['padding-right'] = $gap;
            } else {
                $labelStyles['padding-right'] = $gap;
                $labelStyles['padding-bottom'] = $props['spacing'];
            }

            $html .= sprintf(
                '<td class="totals-label" style="%s">%s:</td>',
                $this->buildStyle($labelStyles),
                $this->escape($item['label'])
            );

            // Value cell
            $valueStyles = [
                'color' => $valueColor,
                'text-align' => 'right',
                'white-space' => 'nowrap',
            ];
            if ($valueMinWidth) {
                $valueStyles['min-width'] = $valueMinWidth;
            }
            if ($valuePadding) {
                $valueStyles['padding'] = $valuePadding;
            } else {
                $valueStyles['padding-bottom'] = $props['spacing'];
            }

            $html .= sprintf(
                '<td class="totals-value" style="%s">%s</td>',
                $this->buildStyle($valueStyles),
                $item['field']
            );

            $html .= '</tr>';
            $rowIndex++;
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * DIVIDER BLOCK
     */
    private function renderDivider(array $props, string $blockId): string
    {
        return sprintf(
            '<hr id="%s-hr" class="block-divider" style="%s" />',
            $blockId,
            $this->buildStyle([
                'border' => 'none',
                'border-top' => "{$props['thickness']} {$props['style']} {$props['color']}",
                'margin-top' => $props['marginTop'],
                'margin-bottom' => $props['marginBottom'],
            ])
        );
    }

    /**
     * SPACER BLOCK
     */
    private function renderSpacer(array $props, string $blockId): string
    {
        return sprintf(
            '<div id="%s-spacer" class="block-spacer" style="%s"></div>',
            $blockId,
            $this->buildStyle(['height' => $props['height']])
        );
    }

    /**
     * QR CODE BLOCK
     * Backend should replace {{QR_CODE:data}} with actual QR code image
     */
    private function renderQRCode(array $props, string $blockId): string
    {
        return sprintf(
            '<div id="%s-qr" class="qr-code-container" style="%s">{{QR_CODE:%s}}</div>',
            $blockId,
            $this->buildStyle(['text-align' => $props['align']]),
            $props['data'] ?? '$invoice.public_url'
        );
    }

    /**
     * SIGNATURE BLOCK
     */
    private function renderSignature(array $props, string $blockId): string
    {
        $html = sprintf(
            '<div id="%s-signature" class="signature-container" style="%s">',
            $blockId,
            $this->buildStyle(['text-align' => $props['align']])
        );

        $html .= '<div class="signature-space" style="margin-bottom: 40px;"></div>';

        if ($props['showLine'] ?? true) {
            $html .= sprintf(
                '<div class="signature-line" style="%s"></div>',
                $this->buildStyle([
                    'border-top' => '1px solid #000',
                    'width' => '200px',
                    'margin-bottom' => '8px',
                    'display' => $props['align'] === 'center' ? 'inline-block' : 'block',
                ])
            );
        }

        $html .= sprintf(
            '<div class="signature-label" style="%s">%s</div>',
            $this->buildStyle([
                'font-size' => $props['fontSize'],
                'color' => $props['color'],
            ]),
            $this->escape($props['label'] ?? '')
        );

        if ($props['showDate'] ?? false) {
            $html .= sprintf(
                '<div style="%s">Date: ________________</div>',
                $this->buildStyle([
                    'font-size' => $props['fontSize'],
                    'color' => $props['color'],
                    'margin-top' => '4px',
                ])
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Convert grid coordinates to absolute pixels
     */
    private function gridToPixels(array $gridPosition): array
    {
        $x = $gridPosition['x'];
        $y = $gridPosition['y'];
        $w = $gridPosition['w'];
        $h = $gridPosition['h'];

        // Calculate column width
        $availableWidth = self::CANVAS_WIDTH - (self::PADDING_H * 2);
        $colWidth = $availableWidth / self::GRID_COLS;

        // Calculate positions including margins
        $left = self::PADDING_H + ($x * $colWidth) + ($x * self::MARGIN_H);
        $top = self::PADDING_V + ($y * self::ROW_HEIGHT) + ($y * self::MARGIN_V);

        // Calculate dimensions
        $width = ($w * $colWidth) + (($w - 1) * self::MARGIN_H);
        $height = ($h * self::ROW_HEIGHT) + (($h - 1) * self::MARGIN_V);

        return [
            'left' => round($left),
            'top' => round($top),
            'width' => round($width),
            'height' => round($height),
        ];
    }

    /**
     * Format position styles for absolute positioning
     */
    private function formatPositionStyle(array $position): string
    {
        return $this->buildStyle([
            'left' => $position['left'] . 'px',
            'top' => $position['top'] . 'px',
            'width' => $position['width'] . 'px',
            'height' => $position['height'] . 'px',
        ]);
    }

    /**
     * Build inline CSS style string from array
     */
    private function buildStyle(array $styles): string
    {
        $parts = [];

        foreach ($styles as $property => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = "{$property}: {$value}";
            }
        }

        return implode('; ', $parts) . ';';
    }

    /**
     * Calculate total document height
     */
    private function calculateDocumentHeight(array $blocks): int
    {
        if (empty($blocks)) {
            return 1122; // A4 height at 96dpi (297mm)
        }

        $maxBottom = 0;

        foreach ($blocks as $block) {
            $position = $this->gridToPixels($block['gridPosition']);
            $bottom = $position['top'] + $position['height'];

            if ($bottom > $maxBottom) {
                $maxBottom = $bottom;
            }
        }

        return max($maxBottom + self::PADDING_V, 1122);
    }

    /**
     * Escape HTML special characters
     */
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

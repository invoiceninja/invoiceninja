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

use App\Utils\Helpers;

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
    /** Stable public selectors shared with the visual invoice designer. */
    private const WIDGET_CLASS_BY_TYPE = [
        'text' => 'invoice-widget--text',
        'image' => 'invoice-widget--image',
        'logo' => 'invoice-widget--logo',
        'table' => 'invoice-widget--table',
        'tasks-table' => 'invoice-widget--tasks-table',
        'divider' => 'invoice-widget--divider',
        'spacer' => 'invoice-widget--spacer',
        'total' => 'invoice-widget--total',
        'qrcode' => 'invoice-widget--qrcode',
        'signature' => 'invoice-widget--signature',
        'client-info' => 'invoice-widget--client-info',
        'client-shipping-info' => 'invoice-widget--client-shipping-info',
        'company-info' => 'invoice-widget--company-info',
        'invoice-details' => 'invoice-widget--invoice-details',
        'public-notes' => 'invoice-widget--public-notes',
        'footer' => 'invoice-widget--footer',
        'terms' => 'invoice-widget--terms',
    ];

    private JsonToSectionsAdapter $adapter;

    /**
     * @param PdfService $pdfService
     * @param array $jsonDesign Complete JSON design with blocks and pageSettings
     */
    public function __construct(private PdfService $pdfService, private array $jsonDesign)
    {
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

        $this->pdfService->document_type = 'json_design';

        // Live rendering must go through generateBaseTemplate() +
        // JsonToSectionsAdapter::toSections(), because:
        //
        //   - generateBaseTemplate() emits <div id="block-X"></div>
        //     placeholders keyed by block id.
        //   - the adapter pulls $entity->line_items, formats rows, and
        //     returns sections keyed by block id.
        //   - PdfBuilder::updateElementProperties() then matches the two by
        //     getElementById() and injects the dynamic content.
        //
        // The new design payload also ships a `body` string with the
        // designer's WYSIWYG snapshot. That body has the visual layout / CSS
        // baked in but uses literal placeholder text ("item.notes",
        // "item.quantity") rather than any template syntax, has only one
        // dummy <tr> regardless of line-item count, and tags its block divs
        // with `class="block"` but no id — so it cannot drive live data
        // rendering. We still consume the design's `documentSettings` (page
        // size / fonts / show_paid_stamp etc.) via DocumentSettingsResolver,
        // and rewrite the output's @page from those resolved settings.
        $sections = $this->adapter->toSections();

        $this->pdfService->designer->template = $this->generateBaseTemplate();

        $builder = new PdfBuilder($this->pdfService);
        $builder->setSections($sections);
        $builder->build();

        return $this->applyPageOverrides($builder->getCompiledHTML(), $this->pdfService->config->settings);
    }

    /**
     * Rewrite the body's @page size/orientation from the resolved settings.
     *
     * The frontend bakes A4/letter into the body's @page rule and never emits
     * landscape, so for any other size or orientation the body is wrong. The
     * resolver gives us the authoritative values (design.documentSettings →
     * company.settings); always rewrite the @page size/orientation pair so
     * the headless-Chrome / Phantom / Gotenberg engines all see the right
     * paper.
     */
    public function applyPageOverrides(string $html, object $settings): string
    {
        $size = $this->cssSizeFor(
            $settings->page_size ?? 'A4',
            $settings->page_layout ?? 'portrait'
        );

        if ($size === null) {
            return $html;
        }

        // Rewrite `size: <anything>;` (or trailing-} case) inside the first
        // @page block. If no @page block exists, prepend one.
        $pattern = '/(@page\s*\{[^}]*?size\s*:\s*)([^;}]+)(\s*[;}])/i';

        if (preg_match($pattern, $html)) {
            return preg_replace($pattern, '${1}' . $size . '${3}', $html, 1);
        }

        $injected = "<style>@page { size: {$size}; }</style>";

        if (stripos($html, '</head>') !== false) {
            return preg_replace('/<\/head>/i', $injected . '</head>', $html, 1);
        }

        return $injected . $html;
    }

    /**
     * Map a settings page_size + orientation pair to the CSS @page size
     * keyword form: `<paper-name> <orientation>` (e.g. "A4 portrait",
     * "Letter landscape"). Browsers/print engines resolve the dimensions
     * themselves — keeping the keywords means we never have to worry about
     * mm conversions or per-engine quirks.
     *
     * Returns null when the size isn't a recognized CSS @page keyword
     * (caller leaves the body's existing @page in place rather than emit
     * something invalid).
     */
    private function cssSizeFor(string $pageSize, string $orientation): ?string
    {
        // Canonical normalized output forms — what we'll actually emit.
        $allowed = [
            'a0'      => 'A0',
            'a1'      => 'A1',
            'a2'      => 'A2',
            'a3'      => 'A3',
            'a4'      => 'A4',
            'a5'      => 'A5',
            'a6'      => 'A6',
            'letter'  => 'Letter',
            'legal'   => 'Legal',
            'tabloid' => 'Tabloid',
            'ledger'  => 'Ledger',
        ];

        $key = strtolower(trim($pageSize));

        if (!isset($allowed[$key])) {
            return null;
        }

        $orient = strtolower(trim($orientation)) === 'landscape' ? 'landscape' : 'portrait';

        return "{$allowed[$key]} {$orient}";
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
        $pageCSS = $this->buildPageCSS($pageSettings, $this->pdfService->config->settings);
        $customStyleElement = $this->customCssStyleElement();

        // Get blocks grouped by row for layout
        $rows = $this->adapter->getRowGroupedBlocks();

        // The paid/cancelled stamp is injected directly (no $-variable in the
        // template) as an absolutely-positioned overlay inside its anchor block
        // — preferably the totals block, otherwise the line-items table. When
        // the entity is not paid/cancelled or the setting is off, $stampHtml is
        // '' and nothing is emitted.
        $stampHtml = $this->paidStampOverlay();
        $stampTargetId = $stampHtml === '' ? null : $this->stampTargetBlockId($blocks);

        // Build container divs with flex row wrapping for multi-block rows.
        // Every block container — single-block placeholder OR a multi-block
        // flex-row — carries `class="json-block"` so the single
        // .json-block { margin-bottom } rule controls inter-block spacing.
        // The `flex-col` children inside a multi-block row don't get the
        // class because margin-bottom on flex children is a no-op (the row
        // height is the tallest column).
        $blockContainers = '';
        foreach ($rows as $rowBlocks) {
            if (count($rowBlocks) === 1) {
                // Single block - render normally. rowAlign still applies to
                // the placeholder div: `margin-left/right: auto` aligns any
                // block-level element with a non-100% width within its parent.
                $block = $rowBlocks[0];
                $alignStyle = $this->rowAlignStyle($block);
                $overlay = ($block['id'] === $stampTargetId) ? $stampHtml : '';
                $relative = $overlay !== '' ? 'position: relative; ' : '';
                $widgetClasses = $this->widgetClasses($block);
                $widgetType = $this->widgetType($block);
                $blockContainers .= "<div id=\"{$block['id']}\" class=\"json-block {$widgetClasses}\" data-widget-type=\"{$widgetType}\" style=\"{$relative}{$alignStyle}\">{$overlay}</div>\n";
            } else {
                // Multiple blocks on same row - wrap in flex container.
                // rowAlign maps to margin-auto on the flex-col, which in a
                // flex container absorbs the available space on the
                // appropriate side(s).
                $blockContainers .= "<div class=\"flex-row json-block\">\n";
                foreach ($rowBlocks as $block) {
                    $widthPercent = ($block['gridPosition']['w'] / 12) * 100;
                    $alignStyle = $this->rowAlignStyle($block);
                    $overlay = ($block['id'] === $stampTargetId) ? $stampHtml : '';
                    $relative = $overlay !== '' ? 'position: relative;' : '';
                    $widgetClasses = $this->widgetClasses($block);
                    $widgetType = $this->widgetType($block);
                    $blockContainers .= "  <div class=\"flex-col\" style=\"width: {$widthPercent}%; {$alignStyle}\">\n";
                    $blockContainers .= "    <div id=\"{$block['id']}\" class=\"{$widgetClasses}\" data-widget-type=\"{$widgetType}\" style=\"{$relative}\">{$overlay}</div>\n";
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
                {$customStyleElement}
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
     * Built-in classes are derived from the block type, keeping the selector
     * contract deterministic; safe user classes are appended when configured.
     */
    private function widgetClasses(array $block): string
    {
        $typeClass = self::WIDGET_CLASS_BY_TYPE[$this->widgetType($block)] ?? 'invoice-widget--unknown';
        $properties = is_array($block['properties'] ?? null) ? $block['properties'] : [];
        $classes = ['invoice-widget', $typeClass];

        foreach ($this->customWidgetClasses($properties['cssClasses'] ?? null) as $className) {
            $classes[] = $className;
        }

        return implode(' ', array_values(array_unique($classes)));
    }

    /**
     * Accept ordinary CSS identifiers only, matching the frontend renderer.
     *
     * @return list<string>
     */
    private function customWidgetClasses(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $tokens = preg_split('/\s+/', trim($value)) ?: [];
        $classes = [];

        foreach ($tokens as $className) {
            if (strlen($className) > 128 || !preg_match('/^-?[_a-zA-Z][-_a-zA-Z0-9]*$/D', $className)) {
                continue;
            }

            $classes[$className] = true;
            if (count($classes) >= 20) {
                break;
            }
        }

        return array_keys($classes);
    }

    /** Never interpolate an unrecognised block type into an HTML attribute. */
    private function widgetType(array $block): string
    {
        $type = $block['type'] ?? '';

        return is_string($type) && isset(self::WIDGET_CLASS_BY_TYPE[$type]) ? $type : 'unknown';
    }

    /** Append design.customCss as its own style element inside the document head. */
    private function customCssStyleElement(): string
    {
        $customCss = $this->jsonDesign['customCss'] ?? '';

        if (!is_string($customCss) || trim($customCss) === '') {
            return '';
        }

        $trimmed = trim($customCss);
        if (preg_match('/\A<style\b[^>]*>(.*)<\/style\s*>\z/is', $trimmed, $matches) === 1) {
            $customCss = trim($matches[1]);
        }

        // Normalize the API fragment rather than trusting style attributes or
        // allowing an embedded closing tag to create arbitrary head markup.
        $customCss = str_ireplace('</style', '<\\/style', $customCss);

        return "<style data-invoice-custom-css>\n{$customCss}\n</style>";
    }

    /**
     * Map a block's top-level `rowAlign` property to inline CSS.
     *
     * left   → margin-right: auto;                     (default)
     * right  → margin-left: auto;
     * center → margin-left: auto; margin-right: auto;
     *
     * Applied to the flex-col wrapper for multi-block rows, and to the
     * placeholder div directly for single-block rows. Both work because
     * `margin: auto` aligns any block-level element with a defined width —
     * either as a flex item (multi-block) or as a normal block (single).
     */
    private function rowAlignStyle(array $block): string
    {
        $align = strtolower((string) ($block['rowAlign'] ?? 'left'));

        return match ($align) {
            'right'  => 'margin-left: auto;',
            'center' => 'margin-left: auto; margin-right: auto;',
            default  => 'margin-right: auto;',
        };
    }

    /**
     * The paid/cancelled stamp overlay, or '' when it should not show.
     *
     * Reuses HtmlEngine's already-resolved gating: $show_paid_stamp is 'flex'
     * only when the entity is paid/cancelled AND the show_paid_stamp setting
     * (mapped from documentSettings.showPaidStamp) is on, and $status_logo
     * carries the translated PAID/CANCELLED markup. We read those computed
     * values rather than re-deriving the status logic here.
     */
    private function paidStampOverlay(): string
    {
        $values = $this->pdfService->html_variables['values'] ?? [];

        if (($values['$show_paid_stamp'] ?? 'none') !== 'flex') {
            return '';
        }

        $statusLogo = (string) ($values['$status_logo'] ?? '');

        if ($statusLogo === '') {
            return '';
        }

        return '<div class="stamp-overlay">' . $statusLogo . '</div>';
    }

    /**
     * The block the stamp anchors over: the totals block when present,
     * otherwise the line-items table block. Null when neither exists.
     */
    private function stampTargetBlockId(array $blocks): ?string
    {
        $tableId = null;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';

            if ($type === 'total') {
                return $block['id'] ?? null;
            }

            if ($tableId === null && $type === 'table') {
                $tableId = $block['id'] ?? null;
            }
        }

        return $tableId;
    }

    /**
     * Build CSS from page settings
     *
     * @param array $pageSettings
     * @return string
     */
    private function buildPageCSS(array $pageSettings, object $settings): string
    {
        $documentSettings = $this->documentSettings();
        $pageSize = $this->resolvePageSizeCSS($pageSettings, $documentSettings, $settings);
        $fontFamily = $this->resolveFontFamily($pageSettings, $documentSettings, $settings);
        $fontSize = $this->resolveFontSize($pageSettings, $documentSettings, $settings);
        $textColor = $pageSettings['textColor'] ?? '#374151';
        $lineHeight = $pageSettings['lineHeight'] ?? '1.5';
        $backgroundColor = $pageSettings['backgroundColor'] ?? '#ffffff';

        // Modern visual-designer payloads store pageMargin* + pagePadding* in
        // documentSettings; both families collapse into the @page margin so the
        // inset repeats on EVERY page — container padding only spaces the first
        // page, leaving page 2+ with no top margin. Legacy pageSettings payloads
        // keep their original @page margin behavior.
        $pageMargins = $this->hasLayoutOverrides()
            ? $this->combinedPageInset()
            : $this->getPageMarginsCSS($pageSettings);

        return <<<CSS
                    @page {
                        size: {$pageSize};
                        margin: {$pageMargins};
                    }
                    body {
                        font-family: {$fontFamily};
                        font-size: {$fontSize};
                        color: {$textColor};
                        line-height: {$lineHeight};
                        background-color: {$backgroundColor};
                        -webkit-font-smoothing: antialiased;
                        -moz-osx-font-smoothing: grayscale;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                        zoom: 80%;
                    }
                    .invoice-container {
                        width: 100%;
                        box-sizing: border-box;
                        padding: 0;
                    }
                    .flex-row {
                        display: flex;
                        flex-wrap: nowrap;
                        gap: 10px;
                    }
                    .flex-col {
                        box-sizing: border-box;
                    }
                    /* Inter-block spacing — each block carries its own
                       bottom margin, no sibling cascade. */
                    .json-block {
                        margin-bottom: 12px;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        table-layout: fixed;
                        overflow-wrap: break-word;
                    }
                    /* Tables flow across pages: rows never split, headers
                       and footers repeat at the top/bottom of each page
                       the table spans. Universal print convention. */
                    thead { display: table-header-group; }
                    tfoot { display: table-footer-group; }
                    tr {
                        break-inside: avoid;
                        page-break-inside: avoid;
                    }
                    .stamp {
                        transform: rotate(12deg);
                        color: #555;
                        font-size: 3rem;
                        font-weight: 700;
                        border: 0.25rem solid #555;
                        display: inline-block;
                        padding: 0.25rem 1rem;
                        text-transform: uppercase;
                        border-radius: 1rem;
                        font-family: 'Courier';
                        mix-blend-mode: multiply;
                        z-index: 200 !important;
                        position: fixed;
                        text-align: center;
                        float: right;

                    }
                    .is-paid {
                        color: #D23;
                        border: 1rem double #D23;
                        transform: rotate(-5deg);
                        font-size: 6rem;
                        font-family: "Open sans", Helvetica, Arial, sans-serif;
                        border-radius: 0;
                        padding: 0.5rem;
                        opacity: 0.2;
                        z-index: 200 !important;
                        position: fixed;
                    }
                    /* Anchors the stamp over its block (totals / line-items)
                       instead of the page. Neutralises the .stamp/.is-paid
                       position: fixed so the overlay's flex centering applies. */
                    .stamp-overlay {
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        pointer-events: none;
                        z-index: 100;
                    }
                    .stamp-overlay .stamp {
                        position: static;
                        float: none;
                    }

            CSS;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSettings(): array
    {
        $documentSettings = $this->jsonDesign['documentSettings'] ?? [];

        return is_array($documentSettings) ? $documentSettings : [];
    }

    /**
     * Modern payloads use documentSettings.pageSize/pageLayout. Legacy JSON
     * payloads may still carry pageSettings.pageSize/orientation.
     */
    private function resolvePageSizeCSS(array $pageSettings, array $documentSettings, object $settings): string
    {
        if (array_key_exists('pageSize', $documentSettings)) {
            $size = $this->cssSizeFor(
                (string) $documentSettings['pageSize'],
                (string) ($documentSettings['pageLayout'] ?? $settings->page_layout ?? 'portrait'),
            );

            if ($size !== null) {
                return $size;
            }
        }

        if ($pageSettings !== []) {
            return $this->getPageSizeCSS($pageSettings);
        }

        return $this->cssSizeFor(
            (string) ($settings->page_size ?? 'A4'),
            (string) ($settings->page_layout ?? 'portrait'),
        ) ?? 'A4 portrait';
    }

    /**
     * documentSettings.primaryFont is the current visual-designer contract.
     * pageSettings.fontFamily remains as a legacy fallback.
     */
    private function resolveFontFamily(array $pageSettings, array $documentSettings, object $settings): string
    {
        if (($documentSettings['primaryFont'] ?? null) !== null && $documentSettings['primaryFont'] !== '') {
            return $this->fontFamilyWithFallback(Helpers::resolveFont((string) $documentSettings['primaryFont'])['name']);
        }

        if (($pageSettings['fontFamily'] ?? null) !== null && $pageSettings['fontFamily'] !== '') {
            return $this->fontFamilyWithFallback((string) $pageSettings['fontFamily']);
        }

        if (($settings->primary_font ?? null) !== null && $settings->primary_font !== '') {
            return $this->fontFamilyWithFallback(Helpers::resolveFont((string) $settings->primary_font)['name']);
        }

        return $this->fontFamilyWithFallback('Inter, sans-serif');
    }

    /**
     * documentSettings.globalFontSize is stored as a number by the visual
     * designer; pageSettings.fontSize is a legacy CSS length string.
     */
    private function resolveFontSize(array $pageSettings, array $documentSettings, object $settings): string
    {
        return $this->cssLength($documentSettings['globalFontSize'] ?? null)
            ?? $this->cssLength($pageSettings['fontSize'] ?? null)
            ?? $this->cssLength($settings->font_size ?? null)
            ?? '12px';
    }

    private function cssLength(mixed $value, string $defaultUnit = 'px'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $this->formatCssNumber((float) $value) . $defaultUnit;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return is_numeric($value)
            ? $this->formatCssNumber((float) $value) . $defaultUnit
            : $value;
    }

    private function formatCssNumber(float $value): string
    {
        return rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
    }

    /**
     * Ensure the font-family chain ends with `Helvetica, sans-serif` so the
     * PDF renderer always has a guaranteed-available fallback. Idempotent —
     * if Helvetica or sans-serif is already in the chain, it isn't added
     * again. Mirrors what the traditional templates emit.
     */
    private function fontFamilyWithFallback(string $fontFamily): string
    {
        $tokens = array_map('trim', explode(',', $fontFamily));
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));

        $hasHelvetica = false;
        $hasSansSerif = false;
        foreach ($tokens as $t) {
            $lower = strtolower($t);
            if ($lower === 'helvetica') {
                $hasHelvetica = true;
            }
            if ($lower === 'sans-serif') {
                $hasSansSerif = true;
            }
        }

        if (!$hasHelvetica) {
            $tokens[] = 'Helvetica';
        }
        if (!$hasSansSerif) {
            $tokens[] = 'sans-serif';
        }

        return implode(', ', $tokens);
    }

    /**
     * Whether the design's documentSettings carries any of the page-margin /
     * page-padding keys. Used to decide whether to render the new
     * design-driven layout or the legacy defaults.
     */
    private function hasLayoutOverrides(): bool
    {
        $docSettings = $this->documentSettings();

        foreach (['pageMarginTop', 'pageMarginRight', 'pageMarginBottom', 'pageMarginLeft',
                  'pagePaddingTop', 'pagePaddingRight', 'pagePaddingBottom', 'pagePaddingLeft'] as $key) {
            if (array_key_exists($key, $docSettings)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build the combined @page margin shorthand by summing pageMargin* +
     * pagePadding* per edge. Both prop families come from the design's
     * documentSettings; missing values default to 0. Result is in `Npx`
     * with the standard top/right/bottom/left order.
     *
     * The collapse exists because CSS @page supports only `margin`, not
     * `padding`. Treating the two prop families as a single visual
     * "page inset" keeps page-level spacing in one CSS rule (@page) and
     * avoids competing margin/padding declarations elsewhere in the sheet.
     */
    private function combinedPageInset(): string
    {
        $docSettings = $this->documentSettings();
        $edges = ['Top', 'Right', 'Bottom', 'Left'];
        $values = [];

        foreach ($edges as $edge) {
            $margin  = (float) ($docSettings['pageMargin' . $edge]  ?? 0);
            $padding = (float) ($docSettings['pagePadding' . $edge] ?? 0);
            $values[] = $this->formatCssNumber($margin + $padding) . 'px';
        }

        return implode(' ', $values);
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

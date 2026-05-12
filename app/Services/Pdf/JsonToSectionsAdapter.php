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
 * Converts JSON-based visual designer output to PdfBuilder sections format
 *
 * This adapter transforms the grid-based JSON block structure from the visual
 * invoice designer into the array-based sections format that PdfBuilder expects.
 * It maintains complete abstraction - PdfBuilder core methods remain unchanged.
 *
 * Architecture:
 * - JSON blocks → PdfBuilder sections array
 * - Grid positioning → CSS/HTML structure
 * - Block properties → Element properties
 * - Maintains data-ref attributes for CSS targeting
 *
 * @see PdfBuilder::setSections()
 * @see tests/Feature/Design/stubs/test_design_1.json
 */
class JsonToSectionsAdapter
{
    /**
     * JSON blocks from visual designer
     */
    private array $jsonBlocks;

    /**
     * Page settings from JSON design
     */
    private array $pageSettings;

    /**
     * PdfService instance for context
     */
    private PdfService $service;

    /**
     * Grouped blocks by row (for layout)
     */
    private ?array $blocksByRow = null;

    /**
     * Blocks sorted by grid position. Cached because section conversion and
     * base-template row grouping both need the same deterministic order.
     */
    private ?array $sortedBlocks = null;

    /**
     * Fetches and inlines user-supplied image URLs so Chromium never fetches
     * untrusted content. Constructor-injected so tests can stub it.
     */
    private ImageFetcher $imageFetcher;

    /**
     * @param array $jsonDesign Complete JSON design with blocks and pageSettings
     * @param PdfService $service
     * @param ImageFetcher|null $imageFetcher Optional override (defaults to a fresh ImageFetcher)
     */
    /**
     * Block-id validation pattern. Block ids are interpolated into HTML `id`
     * attributes and `data-ref` strings without further escaping by the
     * adapter and by JsonDesignService::generateBaseTemplate(). The pattern
     * accepts everything the FE actually emits — kebab-case names, UUIDs,
     * and the dotted/underscored variants — and rejects anything that could
     * break out of an attribute (`"`, `'`, `<`, `>`, `&`, `/`, whitespace,
     * control chars). The 128-char cap prevents extreme attribute sizes.
     */
    private const BLOCK_ID_PATTERN = '/^[A-Za-z0-9._-]{1,128}$/';

    public function __construct(array $jsonDesign, PdfService $service, ?ImageFetcher $imageFetcher = null)
    {
        $this->jsonBlocks = self::filterValidBlocks($jsonDesign['blocks'] ?? []);
        $this->pageSettings = $jsonDesign['pageSettings'] ?? [];
        $this->service = $service;
        $this->imageFetcher = $imageFetcher ?? new ImageFetcher($service->company->company_key ?? null);
    }

    /**
     * Drop blocks with missing or unsafe ids. Static so JsonDesignService
     * can apply the same filter to its generateBaseTemplate input from the
     * same source of truth. Returns the surviving blocks unchanged.
     */
    public static function filterValidBlocks(array $blocks): array
    {
        return array_values(array_filter($blocks, static function ($block) {
            $id = $block['id'] ?? null;
            return is_string($id) && preg_match(self::BLOCK_ID_PATTERN, $id) === 1;
        }));
    }

    /**
     * Convert JSON blocks to PdfBuilder sections format
     *
     * @return array Sections array compatible with PdfBuilder::setSections()
     */
    public function toSections(): array
    {
        $sections = [];

        // Convert each block to a section (no row grouping here - that's done in template)
        foreach ($this->sortedBlocks() as $block) {
            $section = $this->convertBlockToSection($block);
            if ($section !== null) {
                $sections[$block['id']] = $section;
            }
        }

        return $sections;
    }

    /**
     * Get blocks grouped by row for template generation
     *
     * @return array
     */
    public function getRowGroupedBlocks(): array
    {
        if ($this->blocksByRow === null) {
            $this->blocksByRow = $this->groupBlocksIntoRows($this->sortedBlocks());
        }

        return $this->blocksByRow;
    }

    /**
     * Return blocks sorted by grid position, computing the order once for the
     * adapter lifetime.
     */
    private function sortedBlocks(): array
    {
        if ($this->sortedBlocks === null) {
            $this->sortedBlocks = $this->sortBlocksByPosition($this->jsonBlocks);
        }

        return $this->sortedBlocks;
    }

    /**
     * Sort blocks by grid position (Y, then X)
     *
     * @param array $blocks
     * @return array
     */
    private function sortBlocksByPosition(array $blocks): array
    {
        usort($blocks, function ($a, $b) {
            $aY = $a['gridPosition']['y'] ?? 0;
            $bY = $b['gridPosition']['y'] ?? 0;

            if ($aY !== $bY) {
                return $aY - $bY;
            }

            $aX = $a['gridPosition']['x'] ?? 0;
            $bX = $b['gridPosition']['x'] ?? 0;

            return $aX - $bX;
        });

        return $blocks;
    }

    /**
     * Group blocks into rows based on similar Y positions
     * Matches InvoiceDesignRenderer logic - blocks within 1 grid unit are considered same row
     *
     * @param array $blocks
     * @return array Array of rows, each containing array of blocks
     */
    private function groupBlocksIntoRows(array $blocks): array
    {
        $rows = [];
        $currentRow = [];
        $currentY = -1;

        foreach ($blocks as $block) {
            $blockY = $block['gridPosition']['y'] ?? 0;

            // Start new row if Y position differs by >= 1 grid unit
            if ($currentY === -1 || abs($blockY - $currentY) >= 1) {
                if (!empty($currentRow)) {
                    $rows[] = $currentRow;
                }
                $currentRow = [$block];
                $currentY = $blockY;
            } else {
                // Same row - add to current
                $currentRow[] = $block;
            }
        }

        if (!empty($currentRow)) {
            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * Convert a single JSON block to PdfBuilder section format
     *
     * @param array $block
     * @return array|null
     */
    private function convertBlockToSection(array $block): ?array
    {
        return match ($block['type']) {
            'logo', 'image' => $this->convertImageBlock($block),
            'company-info' => $this->convertCompanyInfoBlock($block),
            'client-info' => $this->convertClientInfoBlock($block),
            'client-shipping-info' => $this->convertClientShippingInfoBlock($block),
            'invoice-details' => $this->convertInvoiceDetailsBlock($block),
            'table' => $this->convertTableBlock($block),
            'total' => $this->convertTotalBlock($block),
            // Preset text blocks from the visual designer (same JSON shape as `text`).
            'terms', 'footer', 'public-notes' => $this->convertTextBlock($block),
            'text' => $this->convertTextBlock($block),
            'divider' => $this->convertDividerBlock($block),
            'spacer' => $this->convertSpacerBlock($block),
            'qrcode' => $this->convertQRCodeBlock($block),
            'signature' => $this->convertSignatureBlock($block),
            default => null
        };
    }

    /**
     * Convert logo/image block
     */
    private function convertImageBlock(array $block): array
    {
        $props = $block['properties'];
        $blockId = $block['id'];
        $source = is_string($props['source'] ?? null) ? $props['source'] : '';

        $values = $this->service->html_variables['values'] ?? [];
        $toInline = $block['type'] === 'logo'
            ? strtr($source, $values)
            : $source;

        // Inline the remote asset as a data: URI so Chromium never fetches a
        // user-supplied URL. Fails closed to an empty src if the URL can't be
        // safely retrieved (rejected scheme/host, 3xx, wrong content-type, etc.).
        $src = $toInline === '' ? '' : ($this->imageFetcher->inline($toInline) ?? '');

        return [
            'id' => $blockId,
            'elements' => [
                [
                    'element' => 'div',
                    'properties' => [
                        'data-ref' => "{$blockId}-container",
                        'style' => $this->buildImageContainerStyle($props),
                    ],
                    'elements' => [
                        [
                            'element' => 'img',
                            'properties' => [
                                'src' => $src,
                                'alt' => $block['type'] === 'logo' ? 'Company Logo' : 'Image',
                                'data-ref' => $blockId,
                                'style' => $this->buildImageStyle($props),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert company-info block
     */
    private function convertCompanyInfoBlock(array $block): array
    {
        $props = $block['properties'];
        $elements = [];
        $fieldConfigs = $props['fieldConfigs'] ?? null;

        if ($fieldConfigs && is_array($fieldConfigs)) {
            // New structured format with fieldConfigs
            foreach ($fieldConfigs as $index => $config) {
                $prefix = $config['prefix'] ?? '';
                $variable = $config['variable'] ?? '';
                $suffix = $config['suffix'] ?? '';
                $hideIfEmpty = $config['hideIfEmpty'] ?? true; // Default to hiding empty fields

                $content = '';
                if (!empty($prefix)) {
                    $content .= $prefix;
                }
                $content .= $variable;
                if (!empty($suffix)) {
                    $content .= $suffix;
                }

                $elements[] = [
                    'element' => 'div',
                    'content' => $content,
                    'show_empty' => !$hideIfEmpty, // Invert: show_empty=false means hide when empty
                    'empty_check' => $variable,
                    'properties' => [
                        'data-ref' => "{$block['id']}-field-{$index}",
                        'style' => $this->buildTextStyle($props),
                    ],
                ];
            }
        } else {
            // Legacy content string
            $lines = explode("\n", $props['content'] ?? '');
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $elements[] = [
                    'element' => 'div',
                    'content' => $line,
                    'show_empty' => false, // Hide empty lines
                    'properties' => [
                        'data-ref' => "{$block['id']}-line-{$index}",
                        'style' => $this->buildTextStyle($props),
                    ],
                ];
            }
        }

        return [
            'id' => $block['id'],
            'elements' => $elements,
        ];
    }

    /**
     * Convert client-info block
     */
    private function convertClientInfoBlock(array $block): array
    {
        $props = $block['properties'];
        $elements = [];

        // Optional title
        if ($props['showTitle'] ?? false) {
            $elements[] = [
                'element' => 'div',
                'content' => $props['title'] ?? '',
                'properties' => [
                    'data-ref' => "{$block['id']}-title",
                    'style' => $this->buildTitleStyle($props),
                ],
            ];
        }

        // Field configs
        $fieldConfigs = $props['fieldConfigs'] ?? null;

        if ($fieldConfigs && is_array($fieldConfigs)) {
            foreach ($fieldConfigs as $index => $config) {
                $prefix = $config['prefix'] ?? '';
                $variable = $config['variable'] ?? '';
                $suffix = $config['suffix'] ?? '';
                $hideIfEmpty = $config['hideIfEmpty'] ?? true; // Default to hiding empty fields

                $content = '';
                if (!empty($prefix)) {
                    $content .= $prefix;
                }
                $content .= $variable;
                if (!empty($suffix)) {
                    $content .= $suffix;
                }

                $elements[] = [
                    'element' => 'div',
                    'content' => $content,
                    'show_empty' => !$hideIfEmpty, // Invert: show_empty=false means hide when empty
                    'empty_check' => $variable,
                    'properties' => [
                        'data-ref' => "{$block['id']}-field-{$index}",
                        'style' => $this->buildTextStyle($props),
                    ],
                ];
            }
        } else {
            // Legacy content string
            $lines = explode("\n", $props['content'] ?? '');
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $elements[] = [
                    'element' => 'div',
                    'content' => $line,
                    'show_empty' => false, // Hide empty lines
                    'properties' => [
                        'data-ref' => "{$block['id']}-line-{$index}",
                        'style' => $this->buildTextStyle($props),
                    ],
                ];
            }
        }

        return [
            'id' => $block['id'],
            'elements' => $elements,
        ];
    }

    /**
     * Convert client-shipping-info block.
     *
     * Structurally identical to client-info — the frontend supplies the
     * shipping variables (e.g. $client.shipping_address1) through the same
     * fieldConfigs shape — so we delegate to convertClientInfoBlock.
     *
     * No show_shipping_address gate: the user's design choice (placing this
     * block on the canvas) is the source of truth. The legacy
     * show_shipping_address setting affects traditional designs where the
     * shipping section is implicit; in JSON designs the user is explicit, so
     * if they don't want shipping rendered they remove the block.
     */
    private function convertClientShippingInfoBlock(array $block): array
    {
        return $this->convertClientInfoBlock($block);
    }

    /**
     * Convert invoice-details block
     */
    private function convertInvoiceDetailsBlock(array $block): array
    {
        $props = $block['properties'];
        $items = $props['items'] ?? null;
        $fieldConfigs = $props['fieldConfigs'] ?? null;
        $showLabels = (bool) ($props['showLabels'] ?? true);
        $elements = [];

        if ($fieldConfigs && is_array($fieldConfigs)) {
            // New format: shared fieldConfigs shape (same as company-info /
            // client-info). Each entry contributes one <tr> with a label cell
            // (config.label) and a value cell (config.variable). hideIfEmpty
            // suppresses the row when the variable resolves to empty.
            foreach ($fieldConfigs as $index => $config) {
                $label = $config['label'] ?? '';
                $variable = $config['variable'] ?? '';
                $hideIfEmpty = $config['hideIfEmpty'] ?? true;

                $elements[] = $this->buildInvoiceDetailsRow(
                    $block['id'],
                    $index,
                    $label,
                    $variable,
                    !$hideIfEmpty,
                    $props,
                    $config,
                    $showLabels,
                    $variable,
                );
            }
        } elseif ($items && is_array($items)) {
            // Structured format: items array with label/variable pairs
            foreach ($items as $index => $item) {
                if (!($item['show'] ?? true)) {
                    continue;
                }

                $variable = $item['variable'] ?? '';
                $hideIfEmpty = $item['hideIfEmpty'] ?? true;

                $elements[] = $this->buildInvoiceDetailsRow(
                    $block['id'],
                    $index,
                    $item['label'] ?? '',
                    $variable,
                    !$hideIfEmpty,
                    $props,
                    $item,
                    $showLabels,
                    $variable,
                );
            }
        } elseif (isset($props['content']) && !empty($props['content'])) {
            // Legacy format: content string with "Label: $variable" format
            $lines = explode("\n", $props['content']);

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                $parts = explode(':', $line, 2);
                $label = isset($parts[0]) ? trim($parts[0]) . ':' : '';
                $variable = isset($parts[1]) ? trim($parts[1]) : '';

                $elements[] = $this->buildInvoiceDetailsRow(
                    $block['id'],
                    $index,
                    $label,
                    $variable,
                    false,
                    $props,
                    [],
                    $showLabels,
                    $variable,
                );
            }
        }

        return [
            'id' => $block['id'],
            'elements' => [[
                'element' => 'table',
                'properties' => [
                    'style' => $this->buildInvoiceDetailsTableStyle($props),
                ],
                'elements' => $elements,
            ]],
        ];
    }

    /**
     * Build a single <tr> for the invoice-details block, honoring per-row
     * labelStyle/valueStyle overrides and the showLabels block flag.
     */
    private function buildInvoiceDetailsRow(string $blockId, mixed $index, string $label, string $variable, bool $showEmpty, array $props, array $row, bool $showLabels, ?string $emptyCheck = null): array
    {
        $columnStyles = $this->invoiceDetailsColumnStyles($props);
        $resolver = new CellStyleResolver();
        $context = ['kind' => CellStyleResolver::KIND_INVOICE_DETAILS];

        $valueCell = [
            'element' => 'th',
            'content' => $variable,
            'show_empty' => $showEmpty,
            'properties' => [
                'data-ref' => "{$blockId}-value-{$index}",
                'style' => $this->composeCellStyle(
                    $resolver->resolveValue($props, $row, $context),
                    $columnStyles['value'],
                ),
            ],
        ];

        if (!$showLabels) {
            $rowElement = [
                'element' => 'tr',
                'properties' => ['data-ref' => "{$blockId}-row-{$index}"],
                'elements' => [$valueCell],
            ];

            return $this->withRowEmptyCheck($rowElement, $showEmpty, $emptyCheck ?? $variable);
        }

        $labelCell = [
            'element' => 'th',
            'content' => $label,
            'properties' => [
                'data-ref' => "{$blockId}-label-{$index}",
                'style' => $this->composeCellStyle(
                    $resolver->resolveLabel($props, $row, $context),
                    $columnStyles['label'],
                ),
            ],
        ];

        $rowElement = [
            'element' => 'tr',
            'properties' => ['data-ref' => "{$blockId}-row-{$index}"],
            'elements' => [$labelCell, $valueCell],
        ];

        return $this->withRowEmptyCheck($rowElement, $showEmpty, $emptyCheck ?? $variable);
    }

    /**
     * Attach hide-if-empty metadata to a whole row so labels do not survive
     * after their value cell resolves to empty.
     */
    private function withRowEmptyCheck(array $rowElement, bool $showEmpty, string $emptyCheck): array
    {
        if ($showEmpty) {
            return $rowElement;
        }

        $rowElement['show_empty'] = false;
        $rowElement['empty_check'] = $emptyCheck;

        return $rowElement;
    }

    /**
     * Compute the column-level (non-typography) styles applied uniformly to
     * every label and value cell in an invoice-details block.
     *
     * @return array{label: array<string,string>, value: array<string,string>}
     */
    private function invoiceDetailsColumnStyles(array $props): array
    {
        $align = $props['align'] ?? 'left';
        $labelAlign = $props['labelAlign'] ?? 'left';
        $valueAlign = $props['valueAlign'] ?? $align;
        $labelPadding = $props['labelPadding'] ?? '0';
        $valuePadding = $props['valuePadding'] ?? '0';
        $labelValueGap = $props['labelValueGap'] ?? '12px';
        $rowSpacing = $props['rowSpacing'] ?? '0';
        $lineHeight = $props['lineHeight'] ?? null;

        $label = [
            'text-align' => $labelAlign,
            'padding' => $labelPadding,
            // padding-right asserted after `padding` so labelValueGap survives the shorthand
            'padding-right' => $labelValueGap,
            'padding-bottom' => $rowSpacing,
            'white-space' => 'nowrap',
        ];

        $value = [
            'text-align' => $valueAlign,
            'padding' => $valuePadding,
            'padding-bottom' => $rowSpacing,
            'white-space' => 'nowrap',
        ];

        if (isset($props['valueMinWidth']) && $props['valueMinWidth'] !== '') {
            $value['min-width'] = (string) $props['valueMinWidth'];
        }

        if ($lineHeight !== null && $lineHeight !== '') {
            $label['line-height'] = (string) $lineHeight;
            $value['line-height'] = (string) $lineHeight;
        }

        return ['label' => $label, 'value' => $value];
    }

    /**
     * Build the outer <table> style for an invoice-details block. The
     * `padding` block prop applies here (outer container), per spec.
     */
    private function buildInvoiceDetailsTableStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'border-collapse: collapse';
        $styles[] = 'width: fit-content';
        $styles[] = 'max-width: 100%';

        if (isset($props['padding']) && $props['padding'] !== '') {
            $styles[] = 'padding: ' . $props['padding'];
        }

        if (isset($props['align'])) {
            $align = $props['align'];
            if ($align === 'right') {
                $styles[] = 'margin-left: auto';
            } elseif ($align === 'center') {
                $styles[] = 'margin: 0 auto';
            }
        }

        return implode('; ', $styles) . ';';
    }

    /**
     * Compose the typography map and the column-level overrides into a CSS
     * declaration string. Order matters: typography first (so column-level
     * `padding-right` overrides any padding shorthand from the resolver).
     *
     * @param array<string, ?string> $typography Resolver output (font-size, font-weight, font-style, color)
     * @param array<string, string>  $columnStyles Column-level styles applied uniformly to all rows
     */
    private function composeCellStyle(array $typography, array $columnStyles): string
    {
        $declarations = [];

        foreach ($typography as $property => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $declarations[] = $property . ': ' . $value;
        }

        foreach ($columnStyles as $property => $value) {
            if ($value === '') {
                continue;
            }
            $declarations[] = $property . ': ' . $value;
        }

        return $declarations === [] ? '' : (implode('; ', $declarations) . ';');
    }

    /**
     * Convert table block - builds complete table with custom columns and styling
     */
    private function convertTableBlock(array $block): array
    {
        $props = $block['properties'];
        $columns = $props['columns'] ?? [];

        // Determine table type from column fields
        $tableType = $this->detectTableType($columns);

        // Get filtered line items once; table body generation reuses the same
        // array so large invoices don't walk line_items twice per table block.
        $filteredItems = $this->getFilteredLineItems($tableType);

        // Check if we should hide empty columns
        $hideEmptyColumns = $this->service->config->settings->hide_empty_columns_on_pdf ?? false;

        // Calculate which columns are empty only when the setting can use it.
        $columnVisibility = $hideEmptyColumns
            ? $this->calculateColumnVisibility($columns, $filteredItems)
            : [];

        $visibleColumns = $this->visibleTableColumns($columns, $props, $tableType, $columnVisibility, $hideEmptyColumns);

        // Build header elements (only for visible columns)
        $headerElements = [];
        foreach ($visibleColumns as $column) {
            $headerElements[] = [
                'element' => 'th',
                'content' => $column['header'],
                'properties' => [
                    'data-ref' => $column['header_ref'],
                    'style' => $column['header_style'],
                    'visi' => true, // Mark as visible for border-radius logic
                ],
            ];
        }

        // Build table body rows with only visible columns
        $bodyRows = $this->buildTableBodyRows($visibleColumns, $filteredItems, $tableType, $props);

        return [
            'id' => $block['id'],
            'elements' => [[
                'element' => 'table',
                'properties' => [
                    'style' => $this->buildTableContainerStyle($props),
                    'data-table-type' => $tableType,
                ],
                'elements' => [
                    [
                        'element' => 'thead',
                        'properties' => [
                            'style' => $this->buildTheadStyle($props),
                        ],
                        'elements' => [
                            [
                                'element' => 'tr',
                                'elements' => $headerElements,
                            ],
                        ],
                    ],
                    [
                        'element' => 'tbody',
                        'elements' => $bodyRows,
                    ],
                ],
            ]],
        ];
    }

    /**
     * Precompute the visible column metadata and static styles once per table
     * block rather than once per generated cell.
     *
     * @param array $columns Column definitions from JSON design
     * @param array $props Table properties for styling
     * @param string $tableType 'product' or 'task'
     * @param array $columnVisibility Which columns are empty
     * @param bool $hideEmptyColumns Whether to hide empty columns
     * @return array Visible table column metadata
     */
    private function visibleTableColumns(array $columns, array $props, string $tableType, array $columnVisibility, bool $hideEmptyColumns): array
    {
        $borders = $this->resolveTableBorderProps($props);
        $visibleColumns = [];

        foreach ($columns as $index => $column) {
            $columnId = $column['id'] ?? $index;
            $isEmpty = $columnVisibility[$columnId] ?? false;

            if ($hideEmptyColumns && $isEmpty) {
                continue;
            }

            $visibleColumns[] = [
                'field' => $column['field'] ?? '',
                'header' => $column['header'] ?? '',
                'header_ref' => "{$tableType}_table-{$columnId}-th",
                'cell_ref' => "{$tableType}_table-{$columnId}-td",
                'header_style' => $this->buildTableHeaderStyle($props, $column, $borders),
                // Two body cell variants: the first-row variant suppresses its
                // top stroke when the header bottom is enabled (seam rule).
                'cell_style_first_row' => $this->buildTableCellStyle($props, $column, $borders, true),
                'cell_style' => $this->buildTableCellStyle($props, $column, $borders, false),
            ];
        }

        return $visibleColumns;
    }

    /**
     * Build table body rows using JSON design's custom columns
     *
     * @param array $visibleColumns Precomputed visible column metadata
     * @param array $filteredItems Filtered line items for the table type
     * @param string $tableType 'product' or 'task'
     * @return array Array of row elements
     */
    private function buildTableBodyRows(array $visibleColumns, array $filteredItems, string $tableType, array $props): array
    {
        $rows = [];
        $rowIndex = 0;

        // Build rows
        foreach ($filteredItems as $item) {
            $rowElements = [];
            $isFirstRow = $rowIndex === 0;
            $rowBackground = $this->resolveRowBackground($props, $rowIndex);

            // Per spec §1.1.1: row background is painted on the <tr> for
            // browsers and ALSO on each <td> because dompdf and similar
            // print pipelines drop <tr> backgrounds under border-collapse.
            $cellBgSuffix = $rowBackground !== null
                ? ' background-color: ' . $rowBackground . ';'
                : '';

            foreach ($visibleColumns as $column) {
                $value = $this->getFieldValue($item, $column['field'], $tableType);
                $baseStyle = $isFirstRow ? $column['cell_style_first_row'] : $column['cell_style'];

                $rowElements[] = [
                    'element' => 'td',
                    'content' => $value,
                    'properties' => [
                        'data-ref' => $column['cell_ref'],
                        'style' => $baseStyle . $cellBgSuffix,
                        'visi' => true, // Mark as visible for border-radius logic
                    ],
                ];
            }

            // Apply parseVisibleElements-style logic for first/last cells
            if (!empty($rowElements)) {
                $tr = [
                    'element' => 'tr',
                    'elements' => $rowElements,
                ];
                if ($rowBackground !== null) {
                    $tr['properties'] = ['style' => 'background: ' . $rowBackground . ';'];
                }
                $rows[] = $tr;
                $rowIndex++;
            }
        }

        return $rows;
    }

    /**
     * Get filtered line items by table type
     *
     * @param string $tableType 'product' or 'task'
     * @return array Filtered line items
     */
    private function getFilteredLineItems(string $tableType): array
    {
        $lineItems = $this->service->config->entity->line_items ?? [];
        $filteredItems = [];

        foreach ($lineItems as $item) {
            $itemTypeId = (string) ($item->type_id ?? '1');

            if ($tableType === 'product') {
                // Include products (1) and related types (4, 5, 6)
                if (in_array($itemTypeId, ['1', '4', '5', '6'])) {
                    $filteredItems[] = $item;
                }
            } elseif ($tableType === 'task') {
                // Include only tasks (2)
                if ($itemTypeId === '2') {
                    $filteredItems[] = $item;
                }
            }
        }

        return $filteredItems;
    }

    /**
     * Calculate which columns are empty across all rows
     *
     * @param array $columns Column definitions
     * @param array $items Line items to check
     * @return array Map of columnId => isEmpty (true if all values empty)
     */
    private function calculateColumnVisibility(array $columns, array $items): array
    {
        $visibility = [];

        foreach ($columns as $index => $column) {
            $columnId = $column['id'] ?? $index;
            $field = $column['field'] ?? '';
            $isEmpty = true;

            // Check if any row has a non-empty value for this column
            foreach ($items as $item) {
                $value = $this->getFieldValue($item, $field, '');

                if (!empty($value) && $value !== '0' && $value !== '0.00') {
                    $isEmpty = false;
                    break;
                }
            }

            $visibility[$columnId] = $isEmpty;
        }

        return $visibility;
    }

    /**
     * Get formatted field value from line item
     *
     * @param object $item Line item object
     * @param string $field Field reference (e.g., 'item.product_key')
     * @param string $tableType 'product' or 'task'
     * @return string Formatted value
     */
    private function getFieldValue($item, string $field, string $tableType): string
    {
        // Remove 'item.' prefix if present
        $fieldName = str_replace('item.', '', $field);

        // Map common field names
        $fieldMappings = [
            'product_key' => 'product_key',
            'notes' => 'notes',
            'description' => 'notes',
            'quantity' => 'quantity',
            'cost' => 'cost',
            'unit_cost' => 'cost',
            'line_total' => 'line_total',
            'discount' => 'discount',
            'tax' => 'tax_amount',
            'tax_amount' => 'tax_amount',
            'tax_rate1' => 'tax_rate1',
            'tax_rate2' => 'tax_rate2',
            'tax_rate3' => 'tax_rate3',
        ];

        $actualField = $fieldMappings[$fieldName] ?? $fieldName;

        // Get raw value
        $rawValue = $item->{$actualField} ?? '';

        // Format based on field type
        return $this->formatFieldValue($rawValue, $fieldName, $item);
    }

    /**
     * Format field value based on type
     *
     * @param mixed $value Raw value
     * @param string $fieldName Field name
     * @param object $item Line item for context
     * @return string Formatted value
     */
    private function formatFieldValue($value, string $fieldName, $item): string
    {
        // Handle empty values
        if ($value === null || $value === '') {
            return '';
        }

        // Format based on field type
        switch ($fieldName) {
            case 'quantity':
                return $this->service->config->formatValueNoTrailingZeroes($value);

            case 'cost':
            case 'unit_cost':
                return $this->service->config->formatMoney($value);

            case 'line_total':
            case 'tax_amount':
                return $this->service->config->formatMoneyNoRounding($value);

            case 'discount':
                if (isset($item->is_amount_discount) && $item->is_amount_discount) {
                    return $this->service->config->formatMoney($value);
                } else {
                    return $this->service->config->formatValueNoTrailingZeroes((float) $value) . '%';
                }

                // no break
            case 'tax_rate1':
            case 'tax_rate2':
            case 'tax_rate3':
                return $this->service->config->formatValueNoTrailingZeroes((float) $value) . '%';

            case 'notes':
            case 'description':
                // Process reserved keywords (like :MONTH, :YEAR, etc.)
                $currentDateTime = null;
                if (isset($this->service->config->entity->next_send_date)) {
                    $currentDateTime = \Carbon\Carbon::parse($this->service->config->entity->next_send_date);
                }
                return \App\Utils\Helpers::processReservedKeywords($value, $this->service->config->currency_entity, $currentDateTime);

            default:
                return (string) $value;
        }
    }

    /**
     * Detect table type from column fields (product or task)
     */
    private function detectTableType(array $columns): string
    {
        foreach ($columns as $column) {
            $field = $column['field'] ?? '';
            if (str_starts_with($field, 'item.')) {
                // Generic line items
                return 'product';
            }
        }

        return 'product';
    }

    /**
     * Convert total block
     */
    private function convertTotalBlock(array $block): array
    {
        $props = $block['properties'];
        $items = $props['items'] ?? [];
        $showLabels = (bool) ($props['showLabels'] ?? true);
        $columnStyles = $this->totalColumnStyles($props);
        $resolver = new CellStyleResolver();
        $rowElements = [];

        foreach ($items as $index => $item) {
            if (!($item['show'] ?? true)) {
                continue;
            }

            $field = $item['field'] ?? '';
            $hideIfEmpty = $item['hideIfEmpty'] ?? true;
            $isTotal = (bool) ($item['isTotal'] ?? false);
            $isBalance = (bool) ($item['isBalance'] ?? false);
            $context = [
                'kind' => CellStyleResolver::KIND_TOTAL,
                'isTotal' => $isTotal,
                'isBalance' => $isBalance,
            ];

            $valueCell = [
                'element' => 'td',
                'content' => $field,
                'properties' => [
                    'data-ref' => "{$block['id']}-value-{$index}",
                    'class' => 'totals-value',
                    'style' => $this->composeCellStyle(
                        $resolver->resolveValue($props, $item, $context),
                        $columnStyles['value'],
                    ),
                ],
            ];

            $cells = [];
            if ($showLabels) {
                $cells[] = [
                    'element' => 'td',
                    'content' => ($item['label'] ?? '') . ':',
                    'properties' => [
                        'data-ref' => "{$block['id']}-label-{$index}",
                        'class' => 'totals-label',
                        'style' => $this->composeCellStyle(
                            $resolver->resolveLabel($props, $item, $context),
                            $columnStyles['label'],
                        ),
                    ],
                ];
            }
            $cells[] = $valueCell;

            $rowElement = [
                'element' => 'tr',
                'properties' => [
                    'data-ref' => "{$block['id']}-row-{$index}",
                    'class' => $this->buildTotalRowClass($isTotal, $isBalance),
                ],
                'elements' => $cells,
            ];

            $rowElements[] = $this->withRowEmptyCheck($rowElement, !$hideIfEmpty, $field);
        }

        return [
            'id' => $block['id'],
            'elements' => [[
                'element' => 'table',
                'properties' => [
                    'class' => 'totals-table',
                    'style' => $this->buildTotalContainerStyle($props),
                ],
                'elements' => [
                    [
                        'element' => 'tbody',
                        'elements' => $rowElements,
                    ],
                ],
            ]],
        ];
    }

    /**
     * Column-level styles applied to every label/value cell in the total
     * block. labelAlign/valueAlign default to 'right' (preserving the prior
     * hardcoded behaviour); spacing / labelValueGap / labelPadding /
     * valuePadding / valueMinWidth honor existing keys per spec.
     *
     * @return array{label: array<string,string>, value: array<string,string>}
     */
    private function totalColumnStyles(array $props): array
    {
        $labelAlign = $props['labelAlign'] ?? 'right';
        $valueAlign = $props['valueAlign'] ?? 'right';
        $labelPadding = $props['labelPadding'] ?? '0';
        $valuePadding = $props['valuePadding'] ?? '0';
        $labelValueGap = $props['labelValueGap'] ?? '20px';
        $spacing = $props['spacing'] ?? '4px';

        $label = [
            'text-align' => $labelAlign,
            'padding' => $labelPadding,
            // padding-right asserted after `padding` so labelValueGap survives
            'padding-right' => $labelValueGap,
            'padding-bottom' => $spacing,
            'white-space' => 'nowrap',
        ];

        $value = [
            'text-align' => $valueAlign,
            'padding' => $valuePadding,
            'padding-bottom' => $spacing,
            'white-space' => 'nowrap',
        ];

        if (isset($props['valueMinWidth']) && $props['valueMinWidth'] !== '') {
            $value['min-width'] = (string) $props['valueMinWidth'];
        }

        return ['label' => $label, 'value' => $value];
    }

    /**
     * Convert text block
     */
    private function convertTextBlock(array $block): array
    {
        $props = $block['properties'];
        $content = $props['content'] ?? '';
        $lines = explode("\n", $content);
        $elements = [];

        foreach ($lines as $index => $line) {
            $elements[] = [
                'element' => 'div',
                'content' => trim($line),
                'properties' => [
                    'data-ref' => "{$block['id']}-line-{$index}",
                    'style' => $this->buildTextStyle($props),
                ],
            ];
        }

        return [
            'id' => $block['id'],
            'elements' => $elements,
        ];
    }

    /**
     * Convert divider block
     */
    private function convertDividerBlock(array $block): array
    {
        $props = $block['properties'];

        return [
            'id' => $block['id'],
            'elements' => [
                [
                    'element' => 'hr',
                    'properties' => [
                        'data-ref' => "{$block['id']}-hr",
                        'style' => $this->buildDividerStyle($props),
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert spacer block
     */
    private function convertSpacerBlock(array $block): array
    {
        $props = $block['properties'];

        return [
            'id' => $block['id'],
            'elements' => [
                [
                    'element' => 'div',
                    'content' => '',
                    'properties' => [
                        'data-ref' => "{$block['id']}-spacer",
                        'style' => "height: {$props['height']};",
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert QR code block
     */
    private function convertQRCodeBlock(array $block): array
    {
        $props = $block['properties'];

        return [
            'id' => $block['id'],
            'elements' => [
                [
                    'element' => 'div',
                    'content' => ($props['data'] ?? '$payment_qr_code'),
                    'properties' => [
                        'data-ref' => "{$block['id']}-qr",
                        'data-state' => "encoded-html",
                        'style' => "text-align: " . ($props['align'] ?? 'left') . ";",
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert signature block
     */
    private function convertSignatureBlock(array $block): array
    {
        $props = $block['properties'];
        $elements = [];

        // Signature space
        $elements[] = [
            'element' => 'div',
            'content' => '',
            'properties' => [
                'data-ref' => "{$block['id']}-space",
                'style' => 'margin-bottom: 40px;',
            ],
        ];

        // Signature line
        if ($props['showLine'] ?? true) {
            $elements[] = [
                'element' => 'div',
                'content' => '',
                'properties' => [
                    'data-ref' => "{$block['id']}-line",
                    'style' => $this->buildSignatureLineStyle($props),
                ],
            ];
        }

        // Label
        $elements[] = [
            'element' => 'div',
            'content' => $props['label'] ?? '',
            'properties' => [
                'data-ref' => "{$block['id']}-label",
                'style' => $this->buildSignatureLabelStyle($props),
            ],
        ];

        // Date field
        if ($props['showDate'] ?? false) {
            $elements[] = [
                'element' => 'div',
                'content' => 'Date: ________________',
                'properties' => [
                    'data-ref' => "{$block['id']}-date",
                    'style' => $this->buildSignatureLabelStyle($props),
                ],
            ];
        }

        return [
            'id' => $block['id'],
            'elements' => $elements,
            'properties' => [
                'style' => "text-align: " . ($props['align'] ?? 'left') . ";",
            ],
        ];
    }

    // Style building methods

    private function buildImageContainerStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'text-align: ' . ($props['align'] ?? 'left');
        $styles[] = 'height: 100%';
        $styles[] = 'display: flex';
        $styles[] = 'align-items: center';
        $styles[] = 'justify-content: ' . ($props['align'] ?? 'left');

        return implode('; ', $styles) . ';';
    }

    private function buildImageStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'max-width: ' . ($props['maxWidth'] ?? '100%');
        $styles[] = 'max-height: ' . ($props['maxHeight'] ?? '100%');
        $styles[] = 'object-fit: ' . ($props['objectFit'] ?? 'contain');

        return implode('; ', $styles) . ';';
    }

    private function buildTextStyle(array $props): string
    {
        $styles = [];
        if (isset($props['fontSize'])) {
            $styles[] = 'font-size: ' . $props['fontSize'];
        }
        if (isset($props['fontWeight'])) {
            $styles[] = 'font-weight: ' . $props['fontWeight'];
        }
        if (isset($props['fontStyle'])) {
            $styles[] = 'font-style: ' . $props['fontStyle'];
        }
        if (isset($props['color'])) {
            $styles[] = 'color: ' . $props['color'];
        }
        if (isset($props['align'])) {
            $styles[] = 'text-align: ' . $props['align'];
        }
        if (isset($props['lineHeight'])) {
            $styles[] = 'line-height: ' . $props['lineHeight'];
        }

        return implode('; ', $styles) . ';';
    }

    private function buildTitleStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'font-size: '   . ($props['titleFontSize']   ?? $props['fontSize'] ?? '12px');
        $styles[] = 'font-weight: ' . ($props['titleFontWeight'] ?? 'bold');

        // font-style is conditional — emitting `font-style: normal` by default
        // would override CSS that came in via the cascade.
        if (isset($props['titleFontStyle'])) {
            $styles[] = 'font-style: ' . $props['titleFontStyle'];
        }

        $styles[] = 'color: '      . ($props['titleColor'] ?? $props['color'] ?? '#374151');
        $styles[] = 'text-align: ' . ($props['titleAlign'] ?? $props['align'] ?? 'left');
        $styles[] = 'margin-bottom: 8px';

        return implode('; ', $styles) . ';';
    }


    private function buildTableHeaderStyle(array $props, array $column, array $borders): string
    {
        $styles = [];
        $styles[] = 'padding: ' . ($props['padding'] ?? '8px');
        $styles[] = 'text-align: ' . ($column['align'] ?? 'left');
        if (isset($column['width'])) {
            $styles[] = 'width: ' . $column['width'];
        }

        if (!$borders['showActive']) {
            $styles[] = 'border: none';
        } else {
            $h = $borders['header'];
            $styles[] = 'border-top: '    . $this->buildBorderStroke($h, $h['sides']['top']);
            $styles[] = 'border-right: '  . $this->buildBorderStroke($h, $h['sides']['right']);
            $styles[] = 'border-bottom: ' . $this->buildBorderStroke($h, $h['sides']['bottom']);
            $styles[] = 'border-left: '   . $this->buildBorderStroke($h, $h['sides']['left']);
        }

        // Repeat headerBg on each <th> so PDF engines that drop <thead>/<tr>
        // backgrounds (dompdf and similar) still paint the header row.
        if (isset($props['headerBg']) && is_string($props['headerBg']) && $props['headerBg'] !== '') {
            $styles[] = 'background-color: ' . $props['headerBg'];
        }

        return implode('; ', $styles) . ';';
    }

    private function buildTheadStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'background: ' . ($props['headerBg'] ?? '#F9FAFB');
        $styles[] = 'color: ' . ($props['headerColor'] ?? '#111827');
        $styles[] = 'font-weight: ' . ($props['headerFontWeight'] ?? 'bold');

        return implode('; ', $styles) . ';';
    }

    private function buildTableContainerStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'width: 100%';
        $styles[] = 'border-collapse: collapse';
        $styles[] = 'font-size: ' . ($props['fontSize'] ?? '12px');

        return implode('; ', $styles) . ';';
    }

    private function buildTableCellStyle(array $props, array $column, array $borders, bool $isFirstRow): string
    {
        $styles = [];
        $styles[] = 'padding: ' . ($props['padding'] ?? '8px');
        $styles[] = 'text-align: ' . ($column['align'] ?? 'left');

        if (isset($column['width'])) {
            $styles[] = 'width: ' . $column['width'];
        }

        if (!$borders['showActive']) {
            $styles[] = 'border: none';
        } else {
            $b = $borders['row'];
            $headerBottom = $borders['header']['sides']['bottom'];

            // Seam rule: when the header already draws a bottom border, the
            // first body row must not duplicate it as a top border. Otherwise
            // the body's own top-side toggle decides.
            $topEnabled = $isFirstRow
                ? ($b['sides']['top'] && !$headerBottom)
                : $b['sides']['top'];

            $styles[] = 'border-top: '    . $this->buildBorderStroke($b, $topEnabled);
            $styles[] = 'border-right: '  . $this->buildBorderStroke($b, $b['sides']['right']);
            $styles[] = 'border-bottom: ' . $this->buildBorderStroke($b, $b['sides']['bottom']);
            $styles[] = 'border-left: '   . $this->buildBorderStroke($b, $b['sides']['left']);
        }

        if (isset($props['cellColor'])) {
            $styles[] = 'color: ' . $props['cellColor'];
        }

        // Row background is painted per-row in buildTableBodyRows so that
        // alternating stripes (rowBg / alternateRowBg) can be selected by
        // row index — see resolveRowBackground.

        return implode('; ', $styles) . ';';
    }

    /**
     * Resolve the table border configuration into a normalized structure
     * that header + body cell builders can render directly.
     *
     * Output shape:
     *   [
     *     'showActive' => bool,
     *     'header' => ['color' => string, 'widthPx' => int, 'sides' => [top,right,bottom,left]],
     *     'row'    => ['color' => string, 'widthPx' => int, 'sides' => [top,right,bottom,left]],
     *   ]
     */
    private function resolveTableBorderProps(array $props): array
    {
        return [
            'showActive' => ($props['showBorders'] ?? null) === true,
            'header' => $this->resolveTableRegionBorders($props['headerBorders'] ?? null),
            'row' => $this->resolveTableRegionBorders($props['rowBorders'] ?? null),
        ];
    }

    private function resolveTableRegionBorders($region): array
    {
        if (!is_array($region) || $region === []) {
            return [
                'color' => '#E5E7EB',
                'widthPx' => 1,
                'sides' => ['top' => true, 'right' => true, 'bottom' => true, 'left' => true],
            ];
        }

        $color = (isset($region['color']) && is_string($region['color']) && $region['color'] !== '')
            ? $region['color']
            : '#E5E7EB';

        $widthPx = array_key_exists('width', $region)
            ? $this->coerceBorderWidthPx($region['width'])
            : 1;

        $sidesInput = is_array($region['sides'] ?? null) ? $region['sides'] : [];

        // A side is enabled unless its stored value is *strictly* false.
        // Missing / null / true / 0 / "false" all resolve to true (frontend parity).
        $sides = [
            'top'    => ($sidesInput['top']    ?? null) !== false,
            'right'  => ($sidesInput['right']  ?? null) !== false,
            'bottom' => ($sidesInput['bottom'] ?? null) !== false,
            'left'   => ($sidesInput['left']   ?? null) !== false,
        ];

        return [
            'color' => $color,
            'widthPx' => $widthPx,
            'sides' => $sides,
        ];
    }

    /**
     * Match the frontend's coerceBorderWidthPx: round to nearest int,
     * clamp to [0, 20]. Strings may carry a trailing "px"; non-finite
     * or unparseable inputs fall back to 1.
     */
    private function coerceBorderWidthPx($value): int
    {
        if (is_int($value)) {
            return max(0, min(20, $value));
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                return 1;
            }
            return max(0, min(20, (int) round($value)));
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            $stripped = preg_replace('/px$/i', '', $trimmed);
            // JS parseFloat: pull a leading numeric token, else NaN.
            if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?/', $stripped, $m)) {
                $f = (float) $m[0];
                if (!is_finite($f)) {
                    return 1;
                }
                return max(0, min(20, (int) round($f)));
            }
            return 1;
        }

        return 1;
    }

    private function buildBorderStroke(array $region, bool $sideEnabled): string
    {
        if (!$sideEnabled) {
            return 'none';
        }

        return $region['widthPx'] . 'px solid ' . $region['color'];
    }

    /**
     * Resolve the background colour for a body row at $rowIndex, matching
     * the frontend ternary: alternateRows gates striping with strict ===
     * equality, odd indices use alternateRowBg, even indices use rowBg.
     *
     * Returns null when the resolved value is missing or empty so callers
     * can skip emitting a background declaration entirely (FE parity:
     * `background: undefined` produces no rule).
     */
    private function resolveRowBackground(array $props, int $rowIndex): ?string
    {
        $isStripe = ($props['alternateRows'] ?? null) === true && ($rowIndex % 2) === 1;
        $key = $isStripe ? 'alternateRowBg' : 'rowBg';

        $value = $props[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function buildTotalRowClass(bool $isTotal, bool $isBalance): string
    {
        $classes = ['totals-row'];
        if ($isTotal) {
            $classes[] = 'totals-row-total';
        }
        if ($isBalance) {
            $classes[] = 'totals-row-balance';
        }

        return implode(' ', $classes);
    }

    private function buildTotalContainerStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'border-collapse: collapse';
        $styles[] = 'width: fit-content';
        $styles[] = 'max-width: 100%';

        if (isset($props['align'])) {
            $align = $props['align'];
            if ($align === 'right') {
                $styles[] = 'margin-left: auto';
            } elseif ($align === 'center') {
                $styles[] = 'margin: 0 auto';
            }
        }

        // FE-controlled page-break behavior for the totals table only.
        // Default true: keep the whole totals block on one page (matches
        // the most common user expectation). When explicitly false, the
        // totals table is allowed to flow across pages — individual rows
        // still don't split because the global `tr { break-inside: avoid }`
        // rule applies.
        $keepTogether = (bool) ($props['keepTogether'] ?? true);
        if ($keepTogether) {
            $styles[] = 'break-inside: avoid';
            $styles[] = 'page-break-inside: avoid';
        }

        return implode('; ', $styles) . ';';
    }

    private function buildDividerStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'border: none';
        $styles[] = 'border-top: ' . ($props['thickness'] ?? '1px') . ' ' . ($props['style'] ?? 'solid') . ' ' . ($props['color'] ?? '#E5E7EB');
        $styles[] = 'margin-top: ' . ($props['marginTop'] ?? '10px');
        $styles[] = 'margin-bottom: ' . ($props['marginBottom'] ?? '10px');

        return implode('; ', $styles) . ';';
    }

    private function buildSignatureLineStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'border-top: 1px solid #000';
        $styles[] = 'width: 200px';
        $styles[] = 'margin-bottom: 8px';
        $align = $props['align'] ?? 'left';
        if ($align === 'center') {
            $styles[] = 'display: inline-block';
        }

        return implode('; ', $styles) . ';';
    }

    private function buildSignatureLabelStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'font-size: ' . ($props['fontSize'] ?? '12px');
        $styles[] = 'color: ' . ($props['color'] ?? '#374151');

        return implode('; ', $styles) . ';';
    }
}

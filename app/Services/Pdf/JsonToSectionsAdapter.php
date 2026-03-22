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
    private array $blocksByRow = [];

    /**
     * @param array $jsonDesign Complete JSON design with blocks and pageSettings
     * @param PdfService $service
     */
    public function __construct(array $jsonDesign, PdfService $service)
    {
        $this->jsonBlocks = $jsonDesign['blocks'] ?? [];
        $this->pageSettings = $jsonDesign['pageSettings'] ?? [];
        $this->service = $service;
    }

    /**
     * Convert JSON blocks to PdfBuilder sections format
     *
     * @return array Sections array compatible with PdfBuilder::setSections()
     */
    public function toSections(): array
    {
        $sections = [];

        // Sort blocks by grid position (Y-axis primary, X-axis secondary)
        $sortedBlocks = $this->sortBlocksByPosition($this->jsonBlocks);

        // Convert each block to a section (no row grouping here - that's done in template)
        foreach ($sortedBlocks as $block) {
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
        $sortedBlocks = $this->sortBlocksByPosition($this->jsonBlocks);
        return $this->groupBlocksIntoRows($sortedBlocks);
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
            'invoice-details' => $this->convertInvoiceDetailsBlock($block),
            'table' => $this->convertTableBlock($block),
            'total' => $this->convertTotalBlock($block),
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
                                'src' => $props['source'] ?? '',
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
     * Convert invoice-details block
     */
    private function convertInvoiceDetailsBlock(array $block): array
    {
        $props = $block['properties'];
        $items = $props['items'] ?? null;
        $elements = [];

        if ($items && is_array($items)) {
            // Structured format: items array with label/variable pairs
            foreach ($items as $index => $item) {
                if (!($item['show'] ?? true)) {
                    continue;
                }

                $elements[] = [
                    'element' => 'tr',
                    'properties' => [
                        'data-ref' => "{$block['id']}-row-{$index}",
                    ],
                    'elements' => [
                        [
                            'element' => 'th',
                            'content' => $item['label'] ?? '',
                            'properties' => [
                                'data-ref' => "{$block['id']}-label-{$index}",
                                'style' => $this->buildLabelStyle($props),
                            ],
                        ],
                        [
                            'element' => 'th',
                            'content' => $item['variable'] ?? '',
                            'properties' => [
                                'data-ref' => "{$block['id']}-value-{$index}",
                                'style' => $this->buildValueStyle($props),
                            ],
                        ],
                    ],
                ];
            }
        } elseif (isset($props['content']) && !empty($props['content'])) {
            // Legacy format: content string with "Label: $variable" format
            $lines = explode("\n", $props['content']);

            foreach ($lines as $index => $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Parse "Label: $variable" format
                $parts = explode(':', $line, 2);
                $label = isset($parts[0]) ? trim($parts[0]) . ':' : '';
                $variable = isset($parts[1]) ? trim($parts[1]) : '';

                $elements[] = [
                    'element' => 'tr',
                    'properties' => [
                        'data-ref' => "{$block['id']}-row-{$index}",
                    ],
                    'elements' => [
                        [
                            'element' => 'th',
                            'content' => $label,
                            'properties' => [
                                'data-ref' => "{$block['id']}-label-{$index}",
                                'style' => $this->buildLabelStyle($props),
                            ],
                        ],
                        [
                            'element' => 'th',
                            'content' => $variable,
                            'show_empty' => false, // Hide row if variable is empty
                            'properties' => [
                                'data-ref' => "{$block['id']}-value-{$index}",
                                'style' => $this->buildValueStyle($props),
                            ],
                        ],
                    ],
                ];
            }
        }

        return [
            'id' => $block['id'],
            'elements' => [[
                'element' => 'table',
                'properties' => [
                    'style' => $this->buildTableStyle($props),
                ],
                'elements' => $elements,
            ]],
        ];
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

        // Get filtered line items for visibility calculation
        $filteredItems = $this->getFilteredLineItems($tableType);

        // Calculate which columns are empty (for hiding)
        $columnVisibility = $this->calculateColumnVisibility($columns, $filteredItems);

        // Check if we should hide empty columns
        $hideEmptyColumns = $this->service->config->settings->hide_empty_columns_on_pdf ?? false;

        // Build header elements (only for visible columns)
        $headerElements = [];
        $visibleColumnIndices = [];
        foreach ($columns as $index => $column) {
            $columnId = $column['id'] ?? $index;
            $isEmpty = $columnVisibility[$columnId] ?? false;

            // Skip if column is empty and setting is enabled
            if ($hideEmptyColumns && $isEmpty) {
                continue;
            }

            $visibleColumnIndices[] = $index;
            $headerElements[] = [
                'element' => 'th',
                'content' => $column['header'] ?? '',
                'properties' => [
                    'data-ref' => "{$tableType}_table-{$column['id']}-th",
                    'style' => $this->buildTableHeaderStyle($props, $column),
                    'visi' => true, // Mark as visible for border-radius logic
                ],
            ];
        }

        // Build table body rows with only visible columns
        $bodyRows = $this->buildTableBodyRows($columns, $tableType, $props, $columnVisibility, $hideEmptyColumns);

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
     * Build table body rows using JSON design's custom columns
     *
     * @param array $columns Column definitions from JSON design
     * @param string $tableType 'product' or 'task'
     * @param array $props Table properties for styling
     * @param array $columnVisibility Which columns are empty
     * @param bool $hideEmptyColumns Whether to hide empty columns
     * @return array Array of row elements
     */
    private function buildTableBodyRows(array $columns, string $tableType, array $props, array $columnVisibility, bool $hideEmptyColumns): array
    {
        $rows = [];

        // Get filtered line items
        $filteredItems = $this->getFilteredLineItems($tableType);

        // Build rows
        foreach ($filteredItems as $item) {
            $rowElements = [];

            foreach ($columns as $index => $column) {
                $columnId = $column['id'] ?? $index;
                $isEmpty = $columnVisibility[$columnId] ?? false;

                // Skip if column is empty and setting is enabled
                if ($hideEmptyColumns && $isEmpty) {
                    continue;
                }

                $field = $column['field'] ?? '';
                $value = $this->getFieldValue($item, $field, $tableType);

                $rowElements[] = [
                    'element' => 'td',
                    'content' => $value,
                    'properties' => [
                        'data-ref' => "{$tableType}_table-{$column['id']}-td",
                        'style' => $this->buildTableCellStyle($props, $column),
                        'visi' => true, // Mark as visible for border-radius logic
                    ],
                ];
            }

            // Apply parseVisibleElements-style logic for first/last cells
            if (!empty($rowElements)) {
                $rows[] = [
                    'element' => 'tr',
                    'elements' => $rowElements,
                ];
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
            $itemTypeId = (string)($item->type_id ?? '1');

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
                    return $this->service->config->formatValueNoTrailingZeroes((float)$value) . '%';
                }

            case 'tax_rate1':
            case 'tax_rate2':
            case 'tax_rate3':
                return $this->service->config->formatValueNoTrailingZeroes((float)$value) . '%';

            case 'notes':
            case 'description':
                // Process reserved keywords (like :MONTH, :YEAR, etc.)
                $currentDateTime = null;
                if (isset($this->service->config->entity->next_send_date)) {
                    $currentDateTime = \Carbon\Carbon::parse($this->service->config->entity->next_send_date);
                }
                return \App\Utils\Helpers::processReservedKeywords($value, $this->service->config->currency_entity, $currentDateTime);

            default:
                return (string)$value;
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
        $rowElements = [];

        foreach ($items as $index => $item) {
            if (!($item['show'] ?? true)) {
                continue;
            }

            $isTotal = $item['isTotal'] ?? false;
            $isBalance = $item['isBalance'] ?? false;

            // Create table row with label and value cells
            $rowElements[] = [
                'element' => 'tr',
                'properties' => [
                    'data-ref' => "{$block['id']}-row-{$index}",
                    'class' => $this->buildTotalRowClass($isTotal, $isBalance),
                    'style' => $this->buildTotalRowStyle($props, $isTotal),
                ],
                'elements' => [
                    [
                        'element' => 'td',
                        'content' => $item['label'] . ':',
                        'properties' => [
                            'data-ref' => "{$block['id']}-label-{$index}",
                            'class' => 'totals-label',
                            'style' => $this->buildTotalLabelStyle($props),
                        ],
                    ],
                    [
                        'element' => 'td',
                        'content' => $item['field'],
                        'properties' => [
                            'data-ref' => "{$block['id']}-value-{$index}",
                            'class' => 'totals-value',
                            'style' => $this->buildTotalValueStyle($props, $isTotal, $isBalance),
                        ],
                    ],
                ],
            ];
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
                    'content' => '{{QR_CODE:' . ($props['data'] ?? '$invoice.public_url') . '}}',
                    'properties' => [
                        'data-ref' => "{$block['id']}-qr",
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
        $styles[] = 'font-size: ' . ($props['fontSize'] ?? '12px');
        $styles[] = 'font-weight: ' . ($props['titleFontWeight'] ?? 'bold');
        $styles[] = 'color: ' . ($props['color'] ?? '#374151');
        $styles[] = 'margin-bottom: 8px';

        return implode('; ', $styles) . ';';
    }

    private function buildLabelStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'font-size: ' . ($props['fontSize'] ?? '12px');
        $styles[] = 'color: ' . ($props['labelColor'] ?? '#6B7280');
        $styles[] = 'text-align: ' . ($props['align'] ?? 'left');
        $styles[] = 'padding-right: 12px';
        $styles[] = 'white-space: nowrap';

        return implode('; ', $styles) . ';';
    }

    private function buildValueStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'font-size: ' . ($props['fontSize'] ?? '12px');
        $styles[] = 'color: ' . ($props['color'] ?? '#374151');
        $styles[] = 'text-align: ' . ($props['align'] ?? 'left');

        return implode('; ', $styles) . ';';
    }

    private function buildTableStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'border-collapse: collapse';
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

    private function buildTableHeaderStyle(array $props, array $column): string
    {
        $styles = [];
        $styles[] = 'padding: ' . ($props['padding'] ?? '8px');
        $styles[] = 'text-align: ' . ($column['align'] ?? 'left');
        if (isset($column['width'])) {
            $styles[] = 'width: ' . $column['width'];
        }
        if ($props['showBorders'] ?? true) {
            $styles[] = 'border: 1px solid ' . ($props['borderColor'] ?? '#E5E7EB');
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

    private function buildTableCellStyle(array $props, array $column): string
    {
        $styles = [];
        $styles[] = 'padding: ' . ($props['padding'] ?? '8px');
        $styles[] = 'text-align: ' . ($column['align'] ?? 'left');

        if (isset($column['width'])) {
            $styles[] = 'width: ' . $column['width'];
        }

        if ($props['showBorders'] ?? true) {
            $styles[] = 'border: 1px solid ' . ($props['borderColor'] ?? '#E5E7EB');
        }

        if (isset($props['cellColor'])) {
            $styles[] = 'color: ' . $props['cellColor'];
        }

        if (isset($props['rowBg'])) {
            $styles[] = 'background: ' . $props['rowBg'];
        }

        return implode('; ', $styles) . ';';
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

    private function buildTotalRowStyle(array $props, bool $isTotal): string
    {
        $styles = [];
        $styles[] = 'font-size: ' . ($isTotal ? ($props['totalFontSize'] ?? '14px') : ($props['fontSize'] ?? '12px'));
        $styles[] = 'font-weight: ' . ($isTotal ? ($props['totalFontWeight'] ?? 'bold') : 'normal');

        return implode('; ', $styles) . ';';
    }

    private function buildTotalLabelStyle(array $props): string
    {
        $styles = [];
        $styles[] = 'color: ' . ($props['labelColor'] ?? '#6B7280');
        $styles[] = 'text-align: right';
        $styles[] = 'white-space: nowrap';
        $styles[] = 'padding-right: ' . ($props['labelValueGap'] ?? '20px');
        $styles[] = 'padding-bottom: ' . ($props['spacing'] ?? '4px');

        return implode('; ', $styles) . ';';
    }

    private function buildTotalValueStyle(array $props, bool $isTotal, bool $isBalance): string
    {
        $color = $props['amountColor'] ?? '#374151';
        if ($isTotal) {
            $color = $props['totalColor'] ?? $color;
        }
        if ($isBalance) {
            $color = $props['balanceColor'] ?? $color;
        }

        $styles = [];
        $styles[] = 'color: ' . $color;
        $styles[] = 'text-align: right';
        $styles[] = 'white-space: nowrap';
        $styles[] = 'padding-bottom: ' . ($props['spacing'] ?? '4px');

        return implode('; ', $styles) . ';';
    }

    private function buildTotalContainerStyle(array $props): string
    {
        $styles = [];
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

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

namespace App\Services\Pdf;

/**
 * Resolves typography for a single label/value cell of an invoice-details
 * or total block, following the precedence chain from the JSON design spec.
 *
 * Resolution order per axis:
 *  - Label cell: row.labelStyle.X → row.X (legacy flat) → row-type default → block default
 *  - Value cell: row.valueStyle.X → (total only: row.amountColor for color) → row.X (legacy flat) → row-type default → block default
 *
 * Row-type defaults apply only in the total block via the isTotal/isBalance
 * flags on the row item:
 *  - fontSize:   totalFontSize when isTotal
 *  - fontWeight: totalFontWeight when isTotal
 *  - color:      totalColor when isTotal, balanceColor when isBalance
 *
 * Returns nullable strings; consumers omit unset properties from the emitted
 * CSS so the output is minimal and matches the spec's "additive" promise.
 */
final class CellStyleResolver
{
    public const KIND_INVOICE_DETAILS = 'invoice-details';
    public const KIND_TOTAL = 'total';

    /**
     * @param array $blockProps The block's `properties` map
     * @param array $row        A FieldConfig (invoice-details) or TotalItem (total)
     * @param array $context    ['kind' => self::KIND_*, 'isTotal' => bool, 'isBalance' => bool]
     *
     * @return array{font-size: ?string, font-weight: string, font-style: string, color: ?string}
     */
    public function resolveLabel(array $blockProps, array $row, array $context = []): array
    {
        $kind = $context['kind'] ?? self::KIND_INVOICE_DETAILS;
        $isTotal = (bool) ($context['isTotal'] ?? false);
        $isBalance = (bool) ($context['isBalance'] ?? false);

        $rowStyle = $row['labelStyle'] ?? null;

        return [
            'font-size' => $this->resolveFontSize($rowStyle, $row, $blockProps, $kind, $isTotal),
            'font-weight' => $this->resolveFontWeight($rowStyle, $row, $blockProps, $kind, $isTotal),
            'font-style' => $this->resolveFontStyle($rowStyle, $row),
            'color' => $this->resolveLabelColor($rowStyle, $row, $blockProps, $kind, $isTotal, $isBalance),
        ];
    }

    /**
     * @param array $blockProps The block's `properties` map
     * @param array $row        A FieldConfig (invoice-details) or TotalItem (total)
     * @param array $context    ['kind' => self::KIND_*, 'isTotal' => bool, 'isBalance' => bool]
     *
     * @return array{font-size: ?string, font-weight: string, font-style: string, color: ?string}
     */
    public function resolveValue(array $blockProps, array $row, array $context = []): array
    {
        $kind = $context['kind'] ?? self::KIND_INVOICE_DETAILS;
        $isTotal = (bool) ($context['isTotal'] ?? false);
        $isBalance = (bool) ($context['isBalance'] ?? false);

        $rowStyle = $row['valueStyle'] ?? null;

        return [
            'font-size' => $this->resolveFontSize($rowStyle, $row, $blockProps, $kind, $isTotal),
            'font-weight' => $this->resolveFontWeight($rowStyle, $row, $blockProps, $kind, $isTotal),
            'font-style' => $this->resolveFontStyle($rowStyle, $row),
            'color' => $this->resolveValueColor($rowStyle, $row, $blockProps, $kind, $isTotal, $isBalance),
        ];
    }

    private function resolveFontSize($rowStyle, array $row, array $blockProps, string $kind, bool $isTotal): ?string
    {
        $fromRowStyle = $this->fromCellTypography($rowStyle, 'fontSize');
        if ($fromRowStyle !== null) {
            return $fromRowStyle;
        }

        $fromRow = $this->nonEmpty($row['fontSize'] ?? null);
        if ($fromRow !== null) {
            return $fromRow;
        }

        if ($kind === self::KIND_TOTAL && $isTotal) {
            return $this->nonEmpty($blockProps['totalFontSize'] ?? null)
                ?? $this->nonEmpty($blockProps['fontSize'] ?? null)
                ?? '14px';
        }

        return $this->nonEmpty($blockProps['fontSize'] ?? null);
    }

    private function resolveFontWeight($rowStyle, array $row, array $blockProps, string $kind, bool $isTotal): string
    {
        $fromRowStyle = $this->fromCellTypography($rowStyle, 'fontWeight');
        if ($fromRowStyle !== null) {
            return $fromRowStyle;
        }

        $fromRow = $this->nonEmpty($row['fontWeight'] ?? null);
        if ($fromRow !== null) {
            return $fromRow;
        }

        if ($kind === self::KIND_TOTAL && $isTotal) {
            return $this->nonEmpty($blockProps['totalFontWeight'] ?? null) ?? 'bold';
        }

        return 'normal';
    }

    private function resolveFontStyle($rowStyle, array $row): string
    {
        $fromRowStyle = $this->fromCellTypography($rowStyle, 'fontStyle');
        if ($fromRowStyle !== null) {
            return $fromRowStyle;
        }

        return $this->nonEmpty($row['fontStyle'] ?? null) ?? 'normal';
    }

    private function resolveLabelColor($rowStyle, array $row, array $blockProps, string $kind, bool $isTotal, bool $isBalance): ?string
    {
        $fromRowStyle = $this->fromCellTypography($rowStyle, 'color');
        if ($fromRowStyle !== null) {
            return $fromRowStyle;
        }

        $fromRow = $this->nonEmpty($row['color'] ?? null);
        if ($fromRow !== null) {
            return $fromRow;
        }

        if ($kind === self::KIND_TOTAL) {
            if ($isTotal) {
                return $this->nonEmpty($blockProps['totalColor'] ?? null)
                    ?? $this->nonEmpty($blockProps['labelColor'] ?? null);
            }
            if ($isBalance) {
                return $this->nonEmpty($blockProps['balanceColor'] ?? null)
                    ?? $this->nonEmpty($blockProps['labelColor'] ?? null);
            }
            return $this->nonEmpty($blockProps['labelColor'] ?? null);
        }

        return $this->nonEmpty($blockProps['labelColor'] ?? null)
            ?? $this->nonEmpty($blockProps['color'] ?? null);
    }

    private function resolveValueColor($rowStyle, array $row, array $blockProps, string $kind, bool $isTotal, bool $isBalance): ?string
    {
        $fromRowStyle = $this->fromCellTypography($rowStyle, 'color');
        if ($fromRowStyle !== null) {
            return $fromRowStyle;
        }

        if ($kind === self::KIND_TOTAL) {
            $fromAmountColor = $this->nonEmpty($row['amountColor'] ?? null);
            if ($fromAmountColor !== null) {
                return $fromAmountColor;
            }
        }

        $fromRow = $this->nonEmpty($row['color'] ?? null);
        if ($fromRow !== null) {
            return $fromRow;
        }

        if ($kind === self::KIND_TOTAL) {
            if ($isTotal) {
                return $this->nonEmpty($blockProps['totalColor'] ?? null)
                    ?? $this->nonEmpty($blockProps['amountColor'] ?? null);
            }
            if ($isBalance) {
                return $this->nonEmpty($blockProps['balanceColor'] ?? null)
                    ?? $this->nonEmpty($blockProps['amountColor'] ?? null);
            }
            return $this->nonEmpty($blockProps['amountColor'] ?? null);
        }

        return $this->nonEmpty($blockProps['color'] ?? null);
    }

    /**
     * Returns the value of $key from a CellTypography object, or null if the
     * object is missing, not an array, or the key is empty/null.
     */
    private function fromCellTypography($style, string $key): ?string
    {
        if (!is_array($style)) {
            return null;
        }
        $value = $style[$key] ?? null;
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }

    private function nonEmpty($value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }
        return (string) $value;
    }
}

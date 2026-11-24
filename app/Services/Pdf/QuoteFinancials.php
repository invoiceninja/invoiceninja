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

use DOMDocument;
use DOMElement;
use DOMXPath;

class QuoteFinancials
{
    public function extract(string $html, array $line_items): array
    {
        $document = new DOMDocument();
        $previous_errors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
        $xpath = new DOMXPath($document);
        $groups = ['required' => [], 'optional' => []];

        foreach ($xpath->query('//table[tbody/tr[@data-quote-item-index]]') as $table) {
            $headers = iterator_to_array($xpath->query('./thead/tr/th | ./thead/th', $table));
            foreach ($xpath->query('./tbody/tr[@data-quote-item-index]', $table) as $row) {
                $index = (int) $row->getAttribute('data-quote-item-index');
                if (! isset($line_items[$index]) || ! $this->isVisible($row)) {
                    continue;
                }

                $item = $line_items[$index];
                $group = ($item->is_optional ?? false) ? 'optional' : 'required';
                $groups[$group][$index] ??= [
                    'index' => $index,
                    'label' => strip_tags($item->product_key ?: $item->notes),
                    'description' => [],
                    'details' => [],
                    'total' => null,
                ];

                foreach ($xpath->query('./td', $row) as $column_index => $cell) {
                    $header = $headers[$column_index] ?? null;
                    if (! $header || ! $this->isVisible($header) || ! $this->isVisible($cell)) {
                        continue;
                    }

                    $key = ltrim(base64_decode($header->getAttribute('data-quote-field'), true) ?: '', '$');
                    $key = preg_replace('/^(?:product|task|item)\./', '', $key);
                    $field = ['key' => $key, 'label' => $this->innerHtml($header), 'value' => $this->innerHtml($cell)];
                    if (in_array($key, ['item', 'service', 'product_key', 'description', 'notes'], true)) {
                        $groups[$group][$index]['description'][] = $field;
                    } elseif ($key === 'line_total' && $groups[$group][$index]['total'] === null) {
                        $groups[$group][$index]['total'] = $field;
                    } else {
                        $groups[$group][$index]['details'][] = $field;
                    }
                }
            }
        }

        $totals = [];
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " totals-table-right-side ")]/div') as $row) {
            $cells = $xpath->query('./p[@data-ref]', $row);
            if ($cells->length !== 2 || ! $this->isVisible($cells->item(1))) {
                continue;
            }

            $key = preg_replace('/^totals[-_]table-/', '', $cells->item(1)->getAttribute('data-ref'));
            $totals[] = [
                'key' => $key,
                'label' => $this->innerHtml($cells->item(0)),
                'value' => $this->innerHtml($cells->item(1)),
                'is_total' => $key === 'total',
            ];
        }

        foreach ($xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " totals-table ")]/tbody/tr') as $row) {
            if (! $this->isVisible($row)) {
                continue;
            }

            $value = $xpath->query('./td[contains(concat(" ", normalize-space(@class), " "), " totals-value ")]', $row)->item(0);
            $label = $xpath->query('./td[contains(concat(" ", normalize-space(@class), " "), " totals-label ")]', $row)->item(0);
            if (! $value || ! $this->isVisible($value)) {
                continue;
            }

            $key = ltrim(base64_decode($row->getAttribute('data-quote-field'), true) ?: '', '$');
            $totals[] = [
                'key' => $key,
                'label' => $label ? $this->innerHtml($label) : '',
                'value' => $this->innerHtml($value),
                'is_total' => $key === 'total' || in_array('totals-row-total', explode(' ', $row->getAttribute('class')), true),
            ];
        }

        return ['groups' => $groups, 'totals' => $totals];
    }

    private function isVisible(DOMElement $element): bool
    {
        do {
            if ($element->hasAttribute('hidden') || preg_match('/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*(?:hidden|collapse))\b/i', $element->getAttribute('style'))) {
                return false;
            }
            $element = $element->parentNode;
        } while ($element instanceof DOMElement);

        return true;
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';
        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return Purify::clean(trim($html), true);
    }
}

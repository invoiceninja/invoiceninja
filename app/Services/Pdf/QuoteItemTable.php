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

class QuoteItemTable
{
    public function render(string $html, array $line_items, ?array $selected_options = null): string
    {
        if (! collect($line_items)->contains(fn ($item) => $item->is_optional ?? false)) {
            return $html;
        }

        $document = new DOMDocument();
        $previous_errors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_errors);
        $xpath = new DOMXPath($document);
        $tables = iterator_to_array($xpath->query('//table[tbody/tr[@data-quote-item-index]]'));

        foreach ($tables as $table) {
            $group = $table->getAttribute('data-quote-group');
            if (in_array($group, ['required', 'optional'], true)) {
                if ($group === 'optional' && $selected_options !== null) {
                    foreach ($xpath->query('./tbody/tr[@data-quote-item-index]', $table) as $row) {
                        $index = (int) $row->getAttribute('data-quote-item-index');
                        $this->addCheckbox($document, $row, $line_items[$index], $index, $selected_options);
                    }
                }

                continue;
            }

            $optional_rows = $xpath->query('./tbody/tr[@data-quote-item-index]', $table);
            $has_options = false;
            foreach ($optional_rows as $row) {
                if ($line_items[(int) $row->getAttribute('data-quote-item-index')]->is_optional ?? false) {
                    $has_options = true;
                    break;
                }
            }

            if (! $has_options) {
                continue;
            }

            $optional_table = $table->cloneNode(true);
            $table_id = $table->getAttribute('id');
            if ($table_id !== '') {
                $optional_table->setAttribute('id', $table_id . '-optional');
                $table->setAttribute('data-quote-table', $table_id);
                $optional_table->setAttribute('data-quote-table', $table_id);

                // Preserve ID-based design rules on both copies without duplicate table IDs.
                foreach ($document->getElementsByTagName('style') as $style) {
                    $style->textContent = preg_replace(
                        '/#' . preg_quote($table_id, '/') . '(?![\w-])/',
                        '[data-quote-table="' . $table_id . '"]',
                        $style->textContent
                    );
                }
            }

            foreach ([$table, $optional_table] as $group_index => $group_table) {
                foreach (iterator_to_array($xpath->query('./tbody/tr[@data-quote-item-index]', $group_table)) as $row) {
                    $index = (int) $row->getAttribute('data-quote-item-index');
                    $is_optional = (bool) ($line_items[$index]->is_optional ?? false);
                    if ($is_optional !== (bool) $group_index) {
                        $row->parentNode->removeChild($row);
                        continue;
                    }

                    if ($is_optional && $selected_options !== null) {
                        $this->addCheckbox($document, $row, $line_items[$index], $index, $selected_options);
                    }
                }

                $group_table->setAttribute('data-quote-group', $group_index ? 'optional' : 'required');
                if ($group_index) {
                    $caption = $document->createElement('caption');
                    $caption->appendChild($document->createTextNode(ctrans('texts.optional_items')));
                    $caption->setAttribute('style', 'text-align: start; font-weight: bold; padding: 12px 0; caption-side: top;');
                    $group_table->insertBefore($caption, $group_table->firstChild);
                }
            }

            $table->parentNode->insertBefore($optional_table, $table->nextSibling);
            if ($xpath->query('./tbody/tr[@data-quote-item-index]', $table)->length === 0) {
                $table->parentNode->removeChild($table);
            }
        }

        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $document->removeChild($node);
            }
        }

        return $document->saveHTML();
    }

    private function addCheckbox(DOMDocument $document, DOMElement $row, object $item, int $index, array $selected_options): void
    {
        $cell = null;
        foreach ($row->childNodes as $candidate) {
            if ($candidate instanceof DOMElement && $candidate->tagName === 'td'
                && ! preg_match('/(?:^|;)\s*display\s*:\s*none\b/i', $candidate->getAttribute('style'))) {
                $cell = $candidate;
                break;
            }
        }

        if (! $cell) {
            return;
        }

        $checkbox = $document->createElement('input');
        $checkbox->setAttribute('type', 'checkbox');
        $checkbox->setAttribute('data-quote-option', (string) $index);
        $checkbox->setAttribute('value', (string) $index);
        $checkbox->setAttribute('aria-label', $item->product_key ?: strip_tags($item->notes));
        $checkbox->setAttribute('style', 'width: 20px; height: 20px; vertical-align: middle; margin-inline-end: 8px; cursor: pointer;');
        if (in_array($index, array_map('intval', $selected_options), true)) {
            $checkbox->setAttribute('checked', 'checked');
        }
        $cell->insertBefore($checkbox, $cell->firstChild);
    }
}

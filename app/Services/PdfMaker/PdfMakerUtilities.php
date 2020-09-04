<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker;

use DOMDocument;
use DOMDomError;
use DOMXPath;

trait PdfMakerUtilities
{
    private function initializeDomDocument()
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML($this->design->html());

        $this->document = $document;
        $this->xpath = new DOMXPath($document);
    }

    public function getSection(string $selector, string $section = null)
    {
        $element = $this->document->getElementById($selector);

        if ($section) {
            return $element->getAttribute($section);
        }

        return $element->nodeValue;
    }

    public function getSectionNode(string $selector)
    {
        return $this->document->getElementById($selector);
    }

    public function updateElementProperties(array $elements)
    {
        foreach ($elements as $element) {

            // if (!isset($element['tag']) || !isset($element['id']) || is_null($this->document->getElementById($element['id']))) {
            //     continue;
            // }

            if (isset($element['tag'])) {
                $node = $this->document->getElementsByTagName($element['tag'])->item(0);
            } elseif(!is_null($this->document->getElementById($element['id']))) {
                $node = $this->document->getElementById($element['id']);
            } else {
                continue;
            }

            if (isset($element['properties'])) {
                foreach ($element['properties'] as $property => $value) {
                    $this->updateElementProperty($node, $property, $value);
                }
            }

            if (isset($element['elements'])) {
                $sorted = $this->processChildrenOrder($element['elements']);

                $this->createElementContent($node, $sorted);
            }
        }
    }

    public function processChildrenOrder(array $children)
    {
        $processed = [];

        foreach ($children as $child) {
            if (!isset($child['order'])) {
                $child['order'] = 0;
            }

            $processed[] = $child;
        }

        usort($processed, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        return $processed;
    }

    public function updateElementProperty($element, string $attribute, string $value)
    {
        // We have exception for "hidden" property.
        // hidden="true" or hidden="false" will both hide the element, 
        // that's why we have to create an exception here for this rule.

        if ($attribute == 'hidden' && ($value == false || $value == "false")) {
            return $element;
        }

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        return $element;
    }

    public function createElementContent($element, $children)
    {
        foreach ($children as $child) {
            $_child = $this->document->createElement($child['element'], isset($child['content']) ? $child['content'] : '');
            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $sorted = $this->processChildrenOrder($child['elements']);

                $this->createElementContent($_child, $sorted);
            }
        }
    }

    public function updateVariables(array $variables)
    {
        $html = strtr($this->getCompiledHTML(), $variables['labels']);

        $html = strtr($html, $variables['values']);

        @$this->document->loadHTML($html);

        $this->document->saveHTML();
    }

    public function updateVariable(string $element, string $variable, string $value)
    {
        $element = $this->document->getElementById($element);

        $original = $element->nodeValue;

        $element->nodeValue = '';

        $replaced = strtr($original, [$variable => $value]);

        $element->appendChild(
            $this->document->createTextNode($replaced)
        );

        return $element;
    }

    public function processOptions()
    {
        if (!isset($this->options['all_pages_header']) && !isset($this->options['all_pages_footer'])) {
            return;
        }

        $this->insertPrintCSS();
        $this->wrapIntoTable();
    }

    public function insertPrintCSS()
    {
        $css = <<<EOT
        table.page-container {
            page-break-after: always;
        }
        
        thead.page-header {
            display: table-header-group;
        }

        tfoot.page-footer {
            display: table-footer-group;
        }
        EOT;

        $css_node = $this->document->createTextNode($css);

        $style = $this->document->getElementsByTagName('style')->item(0);

        if ($style) {
            return $style->appendChild($css_node);
        }

        $head = $this->document->getElementsByTagName('head')->item(0);

        if ($head) {
            $style_node = $this->document->createElement('style', $css);

            return $head->appendChild($style_node);
        }
    }

    public function wrapIntoTable()
    {
        $markup = <<<EOT
        <table class="page-container" id="page-container">
            <thead class="page-report">
                <tr>
                    <th class="page-report-cell" id="repeat-header">
                        <!-- Repeating header goes here.. -->
                    </th>
                </tr>
            </thead>
            <tfoot class="report-footer">
                <tr>
                    <td class="report-footer-cell" id="repeat-footer">
                        <!-- Repeating footer goes here -->
                    </td>
                </tr>
            </tfoot>
            <tbody class="report-content">
                <tr>
                    <td class="report-content-cell" id="repeat-content">
                        <!-- Rest of the content goes here -->
                    </td>
                </tr>
            </tbody>
        </table>
        EOT;

        $document = new DOMDocument();
        $document->loadHTML($markup);

        $table = $document->getElementById('page-container');

        $body = $this->document->getElementsByTagName('body')
            ->item(0);

        $body->appendChild(
            $this->document->importNode($table, true)
        );

        for ($i = 0; $i < $body->childNodes->length; $i++) {
            $element = $body->childNodes->item($i);

            if ($element->nodeType !== 1) {
                continue;
            }

            if (
                $element->getAttribute('id') == 'header' ||
                $element->getAttribute('id') == 'footer' ||
                $element->getAttribute('id') === 'page-container'
            ) {
                continue;
            }

            $clone = $element->cloneNode(true);
            $element->parentNode->removeChild($element);

            $this->document->getElementById('repeat-content')->appendChild($clone);
        }

        if (
            $header = $this->document->getElementById('header') &&
            isset($this->data['options']['all_pages_header']) &&
            $this->data['options']['all_pages_header']
        ) {

            $header = $this->document->getElementById('header');
            $clone = $header->cloneNode(true);

            $header->parentNode->removeChild($header);
            $this->document->getElementById('repeat-header')->appendChild($clone);
        }

        if (
            $footer = $this->document->getElementById('footer') &&
            isset($this->data['options']['all_pages_footer']) &&
            $this->data['options']['all_pages_footer']
        ) {
            $footer = $this->document->getElementById('footer');
            $clone = $footer->cloneNode(true);

            $footer->parentNode->removeChild($footer);
            $this->document->getElementById('repeat-footer')->appendChild($clone);
        }
    }
}

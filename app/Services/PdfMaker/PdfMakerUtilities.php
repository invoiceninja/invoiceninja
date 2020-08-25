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
            if (isset($element['tag'])) {
                $node = $this->document->getElementsByTagName($element['tag'])->item(0);
            } else {
                $node = $this->document->getElementById($element['id']);
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
            $_child = $this->document->createElement($child['element'], $child['content']);
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
        if (isset($this->options['print_css']) && $this->options['print_css']) {
            $this->insertPrintCSS();
        }
    }

    public function insertPrintCSS()
    {
        $css = '.page-header,.page-header-space{height:100px}.page-footer,.page-footer-space{height:50px}.page-footer{position:fixed;bottom:0;width:100%;border-top:1px solid #000;background:#ff0}.page-header{position:fixed;top:0;width:100%;border-bottom:1px solid #000;background:#ff0}.page{page-break-after:always}@page{margin:20mm}@media print{thead{display:table-header-group}tfoot{display:table-footer-group}button{display:none}body{margin:0}}';

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
}

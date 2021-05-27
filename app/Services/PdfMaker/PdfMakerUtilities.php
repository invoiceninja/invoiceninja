<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
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
        @$document->loadHTML(mb_convert_encoding($this->design->html(), 'HTML-ENTITIES', 'UTF-8'));

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
            } elseif (!is_null($this->document->getElementById($element['id']))) {
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
                $this->createElementContent($node, $element['elements']);
            }
        }
    }

    public function updateElementProperty($element, string $attribute, ?string $value)
    {
        // We have exception for "hidden" property.
        // hidden="true" or hidden="false" will both hide the element,
        // that's why we have to create an exception here for this rule.

        if ($attribute == 'hidden' && ($value == false || $value == 'false')) {
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
            $contains_html = false;

            if (isset($child['content'])) {
                $child['content'] = nl2br($child['content']);
            }

            // "/\/[a-z]*>/i" -> checks for HTML-like tags:
            // <my-tag></my-tag> => true
            // <my-tag /> => true
            // <my-tag> => false

            if (isset($child['content'])) {
                if (isset($child['is_empty']) && $child['is_empty'] === true) {
                    continue;
                }

                $contains_html = preg_match("/\/[a-z]*>/i", $child['content'], $m) != 0;
            }

            if ($contains_html) {
                // If the element contains the HTML, we gonna display it as is. DOMDocument, is going to
                // encode it for us, preventing any errors on the backend due processing stage.
                // Later, we decode this using Javascript so it looks like it's normal HTML being injected.
                // To get all elements that need frontend decoding, we use 'data-ref' property.

                $_child = $this->document->createElement($child['element'], '');
                $_child->setAttribute('data-state', 'encoded-html');
                $_child->nodeValue = htmlspecialchars($child['content']);
            } else {
                // .. in case string doesn't contain any HTML, we'll just return
                // raw $content.

                $_child = $this->document->createElement($child['element'], isset($child['content']) ? htmlspecialchars($child['content']) : '');
            }

            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $this->createElementContent($_child, $child['elements']);
            }
        }
    }

    public function updateVariables(array $variables)
    {
        $html = strtr($this->getCompiledHTML(), $variables['labels']);

        $html = strtr($html, $variables['values']);

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

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

    public function getEmptyElements(array &$elements, array $variables)
    {
        foreach ($elements as &$element) {
            if (isset($element['elements'])) {
                $this->getEmptyChildrens($element['elements'], $variables);
            }
        }
    }

    public function getEmptyChildrens(array &$children, array $variables)
    {
        foreach ($children as $key => &$child) {
            if (isset($child['content']) && isset($child['show_empty']) && $child['show_empty'] === false) {
                $value = strtr($child['content'], $variables['values']);
                if ($value === '' || $value === '&nbsp;') {
                    $child['is_empty'] = true;
                }
            }

            if (isset($child['elements'])) {
                $this->getEmptyChildrens($child['elements'], $variables);
            }
        }
    }
}

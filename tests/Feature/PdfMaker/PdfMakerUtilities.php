<?php

namespace Tests\Feature\PdfMaker;

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

    public function updateElementProperty(string $element, string $attribute, string $value)
    {
        $element = $this->document->getElementById($element);

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        info('Setting element property failed.');

        return $element;
    }

    public function updateVariables(array $variables)
    {
        $html = strtr($this->getCompiledHTML(), $variables);

        $this->document->loadHTML($html);

        $this->document->saveHTML();
    }

    public function updateVariable(string $element, string $variable, string $value)
    {
        $element = $this->document->getElementById($element);

        $original = $element->nodeValue;

        info([$variable => $value]);

        $element->nodeValue = '';

        $replaced = strtr($original, [$variable => $value]);

        $element->appendChild(
            $this->document->createTextNode($replaced)
        );

        return $element;
    }
}

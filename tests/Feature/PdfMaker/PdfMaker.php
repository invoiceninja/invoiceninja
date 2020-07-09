<?php

namespace Tests\Feature\PdfMaker;

use DOMDocument;

class PdfMaker
{
    use PdfMakerUtilities;

    protected $data;

    public $design;

    public $html;

    public $document;

    private $xpath;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function design(string $design)
    {
        $this->design = new $design();

        $this->initializeDomDocument();

        return $this;
    }

    public function build()
    {
        $raw = $this->design->html();

        foreach ($this->data['template'] as $element) {
            foreach ($element['properties'] as $property => $value) {
                $this->updateElementProperty($element['id'], $property, $value);
            }
        }

        foreach ($this->data['variables'] as $entry) {
            $this->updateVariable($entry['id'], $entry['variable'], $entry['value']);
        }

        return $this;
    }

    public function getCompiledHTML()
    {
        return $this->document->saveHTML();
    }
}

<?php

namespace Tests\Feature\PdfMaker;

class PdfMaker
{
    use PdfMakerUtilities;

    protected $data;

    public $design;

    public $html;

    public $document;

    private $xpath;

    public function __construct(array $data)
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
        if (isset($this->data['template'])) {
            $this->updateElementProperties($this->data['template']);
        }

        if (isset($this->data['variables'])) {
            $this->updateVariables($this->data['variables']);
        }
        
        return $this;
    }

    public function getCompiledHTML()
    {
        return $this->document->saveHTML();
    }
}

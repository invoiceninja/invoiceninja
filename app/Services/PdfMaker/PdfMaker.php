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

class PdfMaker
{
    use PdfMakerUtilities;

    protected $data;

    public $design;

    public $html;

    public $document;

    private $xpath;

    private $filters = [
        '<![CDATA[' => '',
        ']]>' => '',
        '<?xml version="1.0" encoding="utf-8" standalone="yes"??>' => '',
    ];

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

    public function getCompiledHTML($final = false)
    {
        if ($final) {
            $html = $this->document->saveXML();

            $filtered = strtr($html, $this->filters);

            return $filtered;
        }

        return $this->document->saveXML();
    }
}

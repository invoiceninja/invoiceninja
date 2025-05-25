<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\Models\Design;
use Illuminate\Support\Str;

class DesignExtractor
{
    private ?string $html = null;

    public function __construct(private ?string $design = null, private array $options = [])
    {
        if ($design) {
            $design = strtolower($design);

            Str::endsWith('.html', $design) ? $this->design = $design : $this->design = "{$design}.html";
        }

        $this->options = $options;
    }

    public function setHtml(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    private function getHtml(): string
    {
        // nlog($this->design);
        if ($this->html) {
            return $this->html;
        }

        $this->html = file_get_contents(config('ninja.designs.base_path') . $this->design);

        return $this->html;
    }

    public function getSectionHTML(string $section, $id = true): ?string
    {

        $document = new \DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML($this->getHtml());

        if ($id) {
            $element = $document->getElementById($section);
        } else {
            $elements = $document->getElementsByTagName($section);
            $element = $elements[0];
        }

        if ($element) {

            $_document = new \DOMDocument();
            $_document->preserveWhiteSpace = false;
            $_document->formatOutput = true;

            $_document->appendChild(
                $_document->importNode($element, true)
            );

            $html = $_document->saveHTML();

            return str_replace('%24', '$', $html);
        }

        return '';

    }
}

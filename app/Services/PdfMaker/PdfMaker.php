<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\PdfMaker;

use App\Services\Template\TemplateService;
use League\CommonMark\CommonMarkConverter;

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
        '<![CDATA[<![CDATA[' => '',
        ']]]]><![CDATA[>]]>' => '',
        ']]>' => '',
        '<?xml version="1.0" encoding="utf-8" standalone="yes"??>' => '',
    ];

    private $options;

    /** @var CommonMarkConverter */
    protected $commonmark;

    public function __construct(array $data)
    {
        $this->data = $data;

        if (array_key_exists('options', $data)) {
            $this->options = $data['options'];
        }

        $this->commonmark = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            // 'html_input' => 'allow',
        ]);
    }

    public function design(Design $design)
    {
        $this->design = $design;

        $this->initializeDomDocument();

        return $this;
    }

    public function build()
    {
        if (isset($this->data['template']) && isset($this->data['variables'])) {
            $this->getEmptyElements($this->data['template'], $this->data['variables']);
        }

        if (isset($this->data['template'])) {
            $this->updateElementProperties($this->data['template']);
        }

        if(isset($this->options)) {

            $replacements = [];
            $contents = $this->document->getElementsByTagName('ninja');

            $ts = new TemplateService();

            if(isset($this->data['template']['entity'])) {
                try {
                    $entity = $this->data['template']['entity'];
                    $ts->setCompany($entity->company);
                } catch(\Exception $e) {

                }
            }

            $data = $ts->processData($this->options)->getData();
            $twig = $ts->twig;

            foreach ($contents as $content) {

                $template = $content->ownerDocument->saveHTML($content);

                $template = $twig->createTemplate(html_entity_decode($template));
                $template = $template->render($data);

                $f = $this->document->createDocumentFragment();
                $f->appendXML($template);
                $replacements[] = $f;

            }

            foreach($contents as $key => $content) {
                $content->parentNode->replaceChild($replacements[$key], $content);
            }

        }

        if (isset($this->data['variables'])) {
            $this->updateVariables($this->data['variables']);
        }

        return $this;
    }

    /**
     * Final method to get compiled HTML.
     *
     * @param bool $final
     * @return mixed
     */
    public function getCompiledHTML($final = false)
    {

        $html = $this->document->saveHTML();
        // nlog($html);
        return str_replace('%24', '$', $html);
    }
}

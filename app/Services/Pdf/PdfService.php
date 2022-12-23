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

use App\Services\Pdf\PdfConfiguration;
use App\Utils\HtmlEngine;

class PdfService
{

    public $invitation;

    public Company $company;

    public PdfConfiguration $config;

    public PdfBuilder $builder;

    public PdfDesigner $designer;

    public array $html_variables;

    public function __construct($invitation)
    {

        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->config = (new PdfConfiguration($this))->init();

        $this->html_variables = (new HtmlEngine($invitation))->generateLabelsAndValues();

        $this->builder = (new PdfBuilder($this));

        $this->designer = (new PdfDesigner($this))->build();
    }

    public function build()
    {
        $this->builder->build();

    }

    public function getPdf()
    {

    }

    public function getHtml()
    {

    }


        // $state = [
        //     'template' => $template->elements([
        //         'client' => $this->client,
        //         'entity' => $this->entity,
        //         'pdf_variables' => (array) $this->company->settings->pdf_variables,
        //         '$product' => $design->design->product,
        //         'variables' => $variables,
        //     ]),
        //     'variables' => $variables,
        //     'options' => [
        //         'all_pages_header' => $this->entity->client->getSetting('all_pages_header'),
        //         'all_pages_footer' => $this->entity->client->getSetting('all_pages_footer'),
        //     ],
        //     'process_markdown' => $this->entity->client->company->markdown_enabled,
        // ];

        // $maker = new PdfMakerService($state);

        // $maker
        //     ->design($template)
        //     ->build();




}
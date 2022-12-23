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

class PdfService
{

    public $invitation;

    public PdfConfiguration $config;

    public function __construct($invitation)
    {

        $this->invitation = $invitation;

        $this->config = (new PdfConfiguration($this))->init();

    }

    public function getPdf()
    {

    }

    public function getHtml()
    {

    }

}
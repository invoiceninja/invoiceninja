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

namespace App\Services\PdfMaker\Designs\Utilities;

class BaseDesign
{
    public function setup(): void
    {
        if (isset($this->context['client'])) {
            $this->client = $this->context['client'];
        }

        if (isset($this->context['entity'])) {
            $this->entity = $this->context['entity'];
        }
    }
}

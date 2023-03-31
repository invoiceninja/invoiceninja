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

namespace App\DataProviders;

class DesignBlocks
{
    public function __construct(
        public string $includes = '',
        public string $header = '',
        public string $body = '',
        public string $footer = ''
    ) {
    }
}

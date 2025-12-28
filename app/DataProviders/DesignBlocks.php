<?php

/**
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

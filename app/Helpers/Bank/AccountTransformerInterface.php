<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank;

interface AccountTransformerInterface
{
    public function transform($accounts);
}

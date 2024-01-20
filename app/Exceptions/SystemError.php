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

namespace App\Exceptions;

use Exception;

class SystemError extends Exception
{
    public function report()
    {
        // ..
    }

    public function render($request)
    {
        return view('errors.guest', [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);
    }
}

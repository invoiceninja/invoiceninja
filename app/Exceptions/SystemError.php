<?php

/**
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

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

namespace App\Exceptions\Ninja;

use Exception;

class ClientPortalAuthorizationException extends Exception
{
    public function report()
    {
        // ..
    }

    public function render($request)
    {
        return view('errors.client-error', [
            'account' => auth()->guard('contact')->check() ? auth()->guard('contact')->user()->user->account : false,
            'company' => auth()->guard('contact')->check() ? auth()->guard('contact')->user()->company : false,
            'title' => ctrans('texts.error_title'),
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);
    }
}

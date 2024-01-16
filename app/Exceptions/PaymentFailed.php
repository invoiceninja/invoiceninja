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

class PaymentFailed extends Exception
{
    public function report()
    {
        // ..
    }

    public function render($request)
    {
        if (auth()->guard('contact')->user() || ($request->has('cko-session-id') && $request->query('cko-session-id'))) {
            return render('gateways.unsuccessful', [
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ]);
        }

        return response([
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ]);
    }
}

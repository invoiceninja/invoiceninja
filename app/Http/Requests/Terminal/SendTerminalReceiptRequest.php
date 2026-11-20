<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Terminal;

class SendTerminalReceiptRequest extends ActionTerminalPaymentIntentRequest
{
    public function rules()
    {
        return [
            'email' => 'bail|required|email:rfc',
        ];
    }
}

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

class FinalizeTerminalPaymentIntentRequest extends ActionTerminalPaymentIntentRequest
{
    public function authorize(): bool
    {
        return $this->terminalAvailable() && parent::authorize();
    }
}

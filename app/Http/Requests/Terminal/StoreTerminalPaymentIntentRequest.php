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

class StoreTerminalPaymentIntentRequest extends QuoteTerminalPaymentRequest
{
    public function rules()
    {
        return array_merge(parent::rules(), [
            'collection_id' => 'bail|required|uuid',
            'expected_total' => 'bail|required|numeric|gt:0',
        ]);
    }
}

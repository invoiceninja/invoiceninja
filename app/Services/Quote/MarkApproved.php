<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */


namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedApproved;
use App\Models\Quote;

class MarkApproved
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function run($quote)
    {
        /* Return immediately if status is not draft */
        if ($quote->status_id != Quote::STATUS_SENT) {
            return $quote;
        }

        $quote->service()->setStatus(Quote::STATUS_APPROVED)->applyNumber()->save();

        event(new QuoteWasMarkedApproved($quote, $quote->company));

        return $quote;
    }
}

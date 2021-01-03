<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Quote;

use App\Models\Quote;
use App\Utils\Traits\GeneratesCounter;

class ApplyNumber
{
    use GeneratesCounter;

    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function run($quote)
    {
        if ($quote->number != '') {
            return $quote;
        }

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $quote->number = $this->getNextQuoteNumber($this->client);
                break;
            case 'when_sent':
                if ($quote->status_id == Quote::STATUS_SENT) {
                    $quote->number = $this->getNextQuoteNumber($this->client);
                }
                break;

            default:
                // code...
                break;
        }

        return $quote;
    }
}

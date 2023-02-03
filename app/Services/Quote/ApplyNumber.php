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

namespace App\Services\Quote;

use App\Models\Quote;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber
{
    use GeneratesCounter;

    private $client;

    private bool $completed = true;

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
                $quote = $this->trySaving($quote);
                break;
            case 'when_sent':
                if ($quote->status_id == Quote::STATUS_SENT) {
                    $quote = $this->trySaving($quote);
                }
                break;

            default:
                // code...
                break;
        }

        return $quote;
    }

    private function trySaving($quote)
    {
        $x = 1;

        do {
            try {
                $quote->number = $this->getNextQuoteNumber($this->client, $quote);
                $quote->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);

        return $quote;
    }
}

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

namespace App\Services\Quote;

use App\Models\Quote;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;
use App\Models\Client;
class ApplyNumber
{
    use GeneratesCounter;

    private bool $completed = true;

    public function __construct(private Client $client)
    {
    }

    public function run($quote)
    {

        if (trim((string)$quote->number) !== '') {
            return $quote;
        }

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $quote = $this->trySaving($quote);
                break;
            case 'when_sent':
                if (in_array($quote->status_id, [Quote::STATUS_SENT, Quote::STATUS_APPROVED])) {
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

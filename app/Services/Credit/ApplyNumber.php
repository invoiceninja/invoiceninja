<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Credit;

use App\Models\Client;
use App\Models\Credit;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $credit;

    private bool $completed = true;

    public function __construct(Client $client, Credit $credit)
    {
        $this->client = $client;

        $this->credit = $credit;
    }

    public function run()
    {
        if ($this->credit->number != '') {
            return $this->credit;
        }

        $this->trySaving();
        // $this->credit->number = $this->getNextCreditNumber($this->client, $this->credit);

        return $this->credit;
    }

    private function trySaving()
    {
        $x = 1;

        do {
            try {
                $this->credit->number = $this->getNextCreditNumber($this->client, $this->credit);
                $this->credit->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 10) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);
    }
}

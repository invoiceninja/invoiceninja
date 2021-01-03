<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2021. client Ninja LLC (https://clientninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Client;

use App\Models\Client;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Collection;

class ClientService
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function updateBalance(float $amount)
    {
        $this->client->balance += $amount;

        return $this;
    }

    public function updatePaidToDate(float $amount)
    {
        $this->client->paid_to_date += $amount;

        return $this;
    }

    public function adjustCreditBalance(float $amount)
    {
        $this->client->credit_balance += $amount;

        return $this;
    }

    public function getCreditBalance() :float
    {
        $credits = $this->client->credits
                      ->where('is_deleted', false)
                      ->where('balance', '>', 0)
                      ->sortBy('created_at');

        return Number::roundValue($credits->sum('balance'), $this->client->currency()->precision);
    }

    public function getCredits() :Collection
    {
        return $this->client->credits
                  ->where('is_deleted', false)
                  ->where('balance', '>', 0)
                  ->sortBy('created_at');
    }


    public function save() :Client
    {
        $this->client->save();

        return $this->client;
    }
}

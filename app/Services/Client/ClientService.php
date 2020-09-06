<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2020. client Ninja LLC (https://clientninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Client;

use App\Models\Client;

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

    public function save() :Client
    {
        $this->client->save();

        return $this->client;
    }
}

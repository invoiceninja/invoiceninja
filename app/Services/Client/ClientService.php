<?php
/**
 * client Ninja (https://clientninja.com)
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2020. client Ninja LLC (https://clientninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Client;


class ClientService
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function updateBalance($amount)
    {
        $this->client->balance += $amount;

        return $this;
    }

    public function updatePaidToDate($amount)
    {
        $this->client->paid_to_date += $amount;

        return $this;
    }

    public function save()
    {
    	$this->client->save();

    	return $this;
    }
}

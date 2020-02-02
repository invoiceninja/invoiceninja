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


}

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

namespace App\Factory;

use App\DataMapper\ClientSettings;
use App\Models\Client;
use Illuminate\Support\Str;

class ClientFactory
{
    public static function create(int $company_id, int $user_id): Client
    {
        $client = new Client();
        $client->company_id = $company_id;
        $client->user_id = $user_id;
        $client->name = '';
        $client->website = '';
        $client->private_notes = '';
        $client->public_notes = '';
        $client->balance = 0;
        $client->paid_to_date = 0;
        $client->country_id = null;
        $client->is_deleted = 0;
        $client->client_hash = Str::random(40);
        $client->settings = ClientSettings::defaults();
        $client->classification = '';

        return $client;
    }
}

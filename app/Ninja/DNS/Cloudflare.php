<?php

namespace App\Ninja\DNS;

use App\Libraries\Utils;
use App\Models\Account;

class Cloudflare
{

    public static function addDNSRecord(Account $account){

        $zones = json_decode(env('CLOUDFLARE_ZONE_IDS',''), true);

        foreach($zones as $zone)
        {

            $curl = curl_init();
            $jsonEncodedData = json_encode(['type'=>'A', 'name'=>$account->subdomain, 'content'=>env('CLOUDFLARE_TARGET_IP_ADDRESS',''),'proxied'=>true]);

            $opts = [
                CURLOPT_URL => 'https://api.cloudflare.com/client/v4/zones/'.$zone.'/dns_records',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $jsonEncodedData,
                CURLOPT_HTTPHEADER => [ 'Content-Type: application/json',
                    'Content-Length: '.strlen($jsonEncodedData),
                    'X-Auth-Email: '.env('CLOUDFLARE_EMAIL', ''),
                    'X-Auth-Key: '.env('CLOUDFLARE_API_KEY', '')
                ],
            ];

            curl_setopt_array($curl, $opts);

            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($status != 200)
                Utils::logError('unable to update subdomain ' . $account->subdomain . ' @ Cloudflare - '.$result);

        }


    }


}
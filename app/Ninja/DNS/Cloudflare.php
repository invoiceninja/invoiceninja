<?php

namespace App\Ninja\DNS;

use App\Libraries\Utils;
use App\Models\Account;

class Cloudflare
{
    const URL = 'https://api.cloudflare.com/client/v4/';

    public static function addDNSRecord(Account $account){

        addRecordToCloudflare($account->subdomain);

    }

    private function addRecordToCloudflare($subDomain)
    {
        $curl = curl_init();
        $jsonEncodedData = json_encode(['type'=>'A', 'name'=>$subDomain, 'content'=>env('CLOUDFLARE_TARGET_IP_ADDRESS','')]);

        $opts = [
            CURLOPT_URL => URL,
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
            Utils::logError('unable to update subdomain ' . $subDomain . ' @ Cloudflare - '.$result);

    }

}
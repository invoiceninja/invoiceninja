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
            if($account->subdomain != "")
            {
                $jsonEncodedData = json_encode(['type' => 'A', 'name' => $account->subdomain, 'content' => env('CLOUDFLARE_TARGET_IP_ADDRESS', ''), 'proxied' => true]);
                $requestType = 'POST';
                $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/dns_records';
                $response = self::curlCloudFlare($requestType, $url, $jsonEncodedData);
                if ($response['status'] != 200)
                    Utils::logError('Unable to update subdomain ' . $account->subdomain . ' @ Cloudflare - ' . $response['result']['result']);
            }
        }
    }
    public static function removeDNSRecord(Account $account) {
        $zones = json_decode(env('CLOUDFLARE_ZONE_IDS',''), true);
        foreach($zones as $zone)
        {
            if($account->subdomain != "")
            {
                $dnsRecordId = self::getDNSRecord($zone, $account->subdomain);
                //test record exists
                if($dnsRecordId == 0)
                    return;
                $jsonEncodedData = json_encode([]);
                $requestType = 'DELETE';
                $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone . '/dns_records/'. $dnsRecordId .'';
                $response = self::curlCloudFlare($requestType, $url, $jsonEncodedData);
                if ($response['status'] != 200)
                    Utils::logError('Unable to delete subdomain ' . $account->subdomain . ' @ Cloudflare - ' . $response['result']['result']);
            }
        }
    }
    public static function getDNSRecord($zone, $aRecord)
    {
        //harvest the zone_name
        $url = 'https://api.cloudflare.com/client/v4/zones/'. $zone .'/dns_records?type=A&per_page=1';
        $requestType = 'GET';
        $jsonEncodedData = json_encode([]);
        $response = self::curlCloudFlare($requestType, $url, $jsonEncodedData);
        if ($response['status'] != 200)
            Utils::logError('Unable to get the zone name for ' . $aRecord . ' @ Cloudflare - ' . $response['result']['result']);
        $zoneName = $response['result']['result'][0]['zone_name'];
        //get the A record
        $url = 'https://api.cloudflare.com/client/v4/zones/'. $zone .'/dns_records?type=A&name='. $aRecord .'.'. $zoneName .' ';
        $response = self::curlCloudFlare($requestType, $url, $jsonEncodedData);
        if ($response['status'] != 200)
            Utils::logError('Unable to get the record ID for ' . $aRecord . ' @ Cloudflare - ' . $response['result']['result']);
        if(isset($response['result']['result'][0]))
            return $response['result']['result'][0]['id'];
        else
            return 0;
    }
    private static function curlCloudFlare($requestType, $url, $jsonEncodedData)
    {
        $curl = curl_init();
        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $requestType,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $jsonEncodedData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json',
                'Content-Length: ' . strlen($jsonEncodedData),
                'X-Auth-Email: ' . env('CLOUDFLARE_EMAIL', ''),
                'X-Auth-Key: ' . env('CLOUDFLARE_API_KEY', '')
            ],
        ];
        curl_setopt_array($curl, $opts);
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $data['status'] = $status;
        $data['result'] = \json_decode($result, true);
        curl_close($curl);
        return $data;
    }
}

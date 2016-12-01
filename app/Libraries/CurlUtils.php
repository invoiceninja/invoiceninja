<?php namespace App\Libraries;

class CurlUtils
{
    public static function post($url, $data, $headers = false)
    {
        return self::exec('POST', $url, $data, $headers);
    }

    public static function get($url, $headers = false)
    {
        return self::exec('GET', $url, null, $headers);
    }

    public static function exec($method, $url, $data, $headers = false)
    {
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $method,
            CURLOPT_HTTPHEADER => $headers ?: [],
        ];

        if ($data) {
            $opts[CURLOPT_POSTFIELDS] = $data;
        }

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);

        if ($error = curl_error($curl)) {
            Utils::logError('CURL Error #' . curl_errno($curl) . ': ' . $error);
        }

        curl_close($curl);

        return $response;
    }
}

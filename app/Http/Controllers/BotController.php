<?php

namespace App\Http\Controllers;

use Utils;
use Exception;
use App\Libraries\Skype\SkypeResponse;
use App\Libraries\CurlUtils;
use App\Ninja\Intents\BaseIntent;

class BotController extends Controller
{
    public function handleMessage($platform)
    {
        $headers = getallheaders();
        $token = isset($headers['Authorization']) ? $headers['Authorization'] : false;

        if (Utils::isNinjaDev()) {
            // skip validation for testing
        } elseif ( ! $this->validateToken($token)) {
            SkypeResponse::message(trans('texts.not_authorized'));
        }

        $to = '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts';
        //$message = 'new invoice for john for 2 items due tomorrow';
        $message = 'invoice acme client for 3 months support, set due date to next thursday and the discount to 10 percent';
        //$message = 'create a new invoice for john smith with a due date of September 7th';
        //$message = 'create a new invoice for john';
        //$message = 'add 2 tickets and set the due date to yesterday';
        //$message = 'set the po number to 0004';
        //$message = 'set the quantity to 20';
        //$message = 'send the invoice';
        //$message = 'show me my products';

        echo "Message: $message <p>";
        $token = $this->authenticate();

        //try {
            $state = $this->loadState($token);
            $data = $this->parseMessage($message);

            $intent = BaseIntent::createIntent($state, $data);
            $message = $intent->process();
            $state = $intent->getState();

            $this->saveState($token, $state);
            /*
        } catch (Exception $exception) {
            SkypeResponse::message($exception->getMessage());
        }
        */

        $this->sendResponse($token, $to, $message);
    }

    private function authenticate()
    {
        $clientId = env('MSBOT_CLIENT_ID');
        $clientSecret = env('MSBOT_CLIENT_SECRET');
        $scope = 'https://graph.microsoft.com/.default';

        $data = sprintf('grant_type=client_credentials&client_id=%s&client_secret=%s&scope=%s', $clientId, $clientSecret, $scope);

        $response = CurlUtils::post(MSBOT_LOGIN_URL, $data);
        $response = json_decode($response);

        return $response->access_token;
    }

    private function loadState($token)
    {
        $url = sprintf('%s/botstate/skype/conversations/%s', MSBOT_STATE_URL, '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts');

        $headers = [
            'Authorization: Bearer ' . $token
        ];

        $response = CurlUtils::get($url, $headers);
        $data = json_decode($response);

        return json_decode($data->data);
    }

    private function parseMessage($message)
    {
        $appId = env('MSBOT_LUIS_APP_ID');
        $subKey = env('MSBOT_LUIS_SUBSCRIPTION_KEY');
        $message = rawurlencode($message);

        $url = sprintf('%s?id=%s&subscription-key=%s&q=%s', MSBOT_LUIS_URL, $appId, $subKey, $message);
        $data = file_get_contents($url);
        $data = json_decode($data);

        return $data;
    }

    private function saveState($token, $data)
    {
        $url = sprintf('%s/botstate/skype/conversations/%s', MSBOT_STATE_URL, '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts');

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $data = '{ eTag: "*", data: "' . addslashes(json_encode($data)) . '" }';

        CurlUtils::post($url, $data, $headers);
    }

    private function sendResponse($token, $to, $message)
    {
        $url = sprintf('%s/conversations/%s/activities/', SKYPE_API_URL, $to);

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

        $response = CurlUtils::post($url, $message, $headers);

        echo "<pre>" . htmlentities(json_encode(json_decode($message), JSON_PRETTY_PRINT)) . "</pre>";
        var_dump($response);
    }

    private function validateToken($token)
    {
        if ( ! $token) {
            return false;
        }

        // https://blogs.msdn.microsoft.com/tsmatsuz/2016/07/12/developing-skype-bot/
        // 0:Invalid, 1:Valid
        $token_valid = 0;

        // 1 separate token by dot (.)
        $token_arr = explode('.', $token);
        $headers_enc = $token_arr[0];
        $claims_enc = $token_arr[1];
        $sig_enc = $token_arr[2];

        // 2 base 64 url decoding
        $headers_arr = json_decode($this->base64_url_decode($headers_enc), TRUE);
        $claims_arr = json_decode($this->base64_url_decode($claims_enc), TRUE);
        $sig = $this->base64_url_decode($sig_enc);

        // 3 get key list
        $keylist = file_get_contents('https://api.aps.skype.com/v1/keys');
        $keylist_arr = json_decode($keylist, TRUE);
        foreach($keylist_arr['keys'] as $key => $value) {

            // 4 select one key (which matches)
            if($value['kid'] == $headers_arr['kid']) {

                // 5 get public key from key info
                $cert_txt = '-----BEGIN CERTIFICATE-----' . "\n" . chunk_split($value['x5c'][0], 64) . '-----END CERTIFICATE-----';
                $cert_obj = openssl_x509_read($cert_txt);
                $pkey_obj = openssl_pkey_get_public($cert_obj);
                $pkey_arr = openssl_pkey_get_details($pkey_obj);
                $pkey_txt = $pkey_arr['key'];

                // 6 verify signature
                $token_valid = openssl_verify($headers_enc . '.' . $claims_enc, $sig, $pkey_txt, OPENSSL_ALGO_SHA256);
            }
        }

        // 7 show result
        return ($token_valid == 1);
    }

    private function base64_url_decode($arg) {
        $res = $arg;
        $res = str_replace('-', '+', $res);
        $res = str_replace('_', '/', $res);
        switch (strlen($res) % 4) {
            case 0:
                break;
            case 2:
                $res .= "==";
                break;
            case 3:
                $res .= "=";
                break;
            default:
                break;
        }
        $res = base64_decode($res);
        return $res;
    }

}

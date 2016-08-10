<?php

namespace App\Http\Controllers;

use Exception;
use App\Libraries\Skype\SkypeResponse;
use App\Libraries\CurlUtils;
use App\Ninja\Intents\BaseIntent;

class BotController extends Controller
{
    public function handleMessage($platform)
    {
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
        dd($data);
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

}

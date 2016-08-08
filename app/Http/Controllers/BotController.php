<?php

namespace App\Http\Controllers;

use App\Libraries\CurlUtils;
use Exception;
use App\Ninja\Intents\BaseIntent;

class BotController extends Controller
{
    public function handleMessage($platform)
    {
        $to = '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts';
        $message = 'add 8 tickets';
        //$message = view('bots.skype.message', ['message' => $message])->render();
        //return $this->sendResponse($to, $message);

        $token = $this->authenticate();

        //try {
            $state = $this->loadState($token);
            var_dump($state);

            $data = $this->parseMessage($message);

            $intent = BaseIntent::createIntent($state, $data);
            $message = $intent->process();
            $state = $intent->getState();

            $this->saveState($token, $state);
            /*
        } catch (Exception $exception) {
            $message = view('bots.skype.message', [
                'message' => $exception->getMessage()
            ])->render();
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

        var_dump($data->compositeEntities);

        return $data;
    }

    private function saveState($token, $data)
    {
        $url = sprintf('%s/botstate/skype/conversations/%s', MSBOT_STATE_URL, '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts');
        var_dump($url);

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $data = '{ eTag: "*", data: "' . addslashes(json_encode($data)) . '" }';
        //$data = '{ eTag: "*", data: "" }';
        var_dump($data);
        $response = CurlUtils::post($url, $data, $headers);

        var_dump($response);
    }

    private function sendResponse($token, $to, $message)
    {
        $url = sprintf('%s/conversations/%s/activities/', SKYPE_API_URL, $to);

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

        $response = CurlUtils::post($url, $message, $headers);

        var_dump($response);
    }

}

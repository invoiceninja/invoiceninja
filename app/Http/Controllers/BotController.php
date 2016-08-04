<?php

namespace App\Http\Controllers;

use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Intents\BaseIntent;

class BotController extends Controller
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo)
    {
        //parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
    }

    public function handleMessage($platform)
    {
        //$message = 'Here\'s your <a href=\'http://ninja.dev/invoices/1/edit\'>invoice</a>';
        $message = 'create an invoice for test client';
        $to = '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts';

        $appId = env('MSBOT_LUIS_APP_ID');
        $subKey = env('MSBOT_LUIS_SUBSCRIPTION_KEY');
        $message = rawurlencode($message);

        $url = sprintf('%s?id=%s&subscription-key=%s&q=%s', MSBOT_LUIS_URL, $appId, $subKey, $message);
        $data = file_get_contents($url);
        $data = json_decode($data);

        if ( ! count($data->intents)) {
            return false;
        }

        $intent = $data->intents[0];
        $intentType = $intent->intent;

        $intent = BaseIntent::createIntent($intentType, $data->entities);
        $message = $intent->process();

        $this->sendResponse($to, $message);
    }

    private function sendResponse($to, $message)
    {
        $clientId = env('MSBOT_CLIENT_ID');
        $clientSecret = env('MSBOT_CLIENT_SECRET');
        $scope = 'https://graph.microsoft.com/.default';

        $data = sprintf('grant_type=client_credentials&client_id=%s&client_secret=%s&scope=%s', $clientId, $clientSecret, $scope);
        $curl = curl_init();

        $opts = [
            CURLOPT_URL => MSBOT_LOGIN_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ];

        curl_setopt_array($curl, $opts);
        curl_exec($curl);


        $response = json_decode(curl_exec($curl));
        $token = $response->access_token;
        print_r($token);

        $url = sprintf('%s/conversations/%s/activities/', SKYPE_API_URL, $to);

        $opts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => 'POST',
            CURLOPT_POSTFIELDS => $message,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ];

        curl_setopt_array($curl, $opts);
        $response = curl_exec($curl);
        curl_close($curl);

        var_dump($response);
    }

}

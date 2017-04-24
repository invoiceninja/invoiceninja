<?php

namespace App\Http\Controllers;

use App\Libraries\CurlUtils;
use App\Libraries\Skype\SkypeResponse;
use App\Models\SecurityCode;
use App\Models\User;
use App\Ninja\Intents\BaseIntent;
use App\Ninja\Mailers\UserMailer;
use Auth;
use Cache;
use DB;
use Exception;
use Input;
use Utils;

class BotController extends Controller
{
    protected $userMailer;

    public function __construct(UserMailer $userMailer)
    {
        $this->userMailer = $userMailer;
    }

    public function handleMessage($platform)
    {
        abort(404);

        $input = Input::all();
        $botUserId = $input['from']['id'];

        if (! $token = $this->authenticate($input)) {
            return SkypeResponse::message(trans('texts.not_authorized'));
        }

        try {
            if ($input['type'] === 'contactRelationUpdate') {
                // brand new user, ask for their email
                if ($input['action'] === 'add') {
                    $response = SkypeResponse::message(trans('texts.bot_get_email'));
                    $state = BOT_STATE_GET_EMAIL;
                } elseif ($input['action'] === 'remove') {
                    $this->removeBot($botUserId);
                    $this->saveState($token, false);

                    return RESULT_SUCCESS;
                }
            } else {
                $state = $this->loadState($token);
                $text = strip_tags($input['text']);

                // user gaves us their email
                if ($state === BOT_STATE_GET_EMAIL) {
                    if ($this->validateEmail($text, $botUserId)) {
                        $response = SkypeResponse::message(trans('texts.bot_get_code'));
                        $state = BOT_STATE_GET_CODE;
                    } else {
                        $response = SkypeResponse::message(trans('texts.email_not_found', ['email' => $text]));
                    }
                // user sent the scurity code
                } elseif ($state === BOT_STATE_GET_CODE) {
                    if ($this->validateCode($text, $botUserId)) {
                        $response = SkypeResponse::message(trans('texts.bot_welcome') . trans('texts.bot_help_message'));
                        $state = BOT_STATE_READY;
                    } else {
                        $response = SkypeResponse::message(trans('texts.invalid_code'));
                    }
                // regular chat message
                } else {
                    if ($text === 'help') {
                        $response = SkypeResponse::message(trans('texts.bot_help_message'));
                    } elseif ($text == 'status') {
                        $response = SkypeResponse::message(trans('texts.intent_not_supported'));
                    } else {
                        if (! $user = User::whereBotUserId($botUserId)->with('account')->first()) {
                            return SkypeResponse::message(trans('texts.not_authorized'));
                        }

                        Auth::onceUsingId($user->id);
                        $user->account->loadLocalizationSettings();

                        $data = $this->parseMessage($text);
                        $intent = BaseIntent::createIntent($platform, $state, $data);
                        $response = $intent->process();
                        $state = $intent->getState();
                    }
                }
            }

            $this->saveState($token, $state);
        } catch (Exception $exception) {
            $response = SkypeResponse::message($exception->getMessage());
        }

        $this->sendResponse($token, $botUserId, $response);

        return RESULT_SUCCESS;
    }

    public function handleCommand()
    {
        $command = request()->command;
        $data = $this->parseMessage($command);

        try {
            $intent = BaseIntent::createIntent(BOT_PLATFORM_WEB_APP, false, $data);
            return $intent->process();
        } catch (Exception $exception) {
            $message = sprintf('"%s"<br/>%s', $command, $exception->getMessage());
            return redirect()->back()->withWarning($message);
        }
    }

    private function authenticate($input)
    {
        $token = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : false;

        if (Utils::isNinjaDev()) {
            // skip validation for testing
        } elseif (! $this->validateToken($token)) {
            return false;
        }

        if ($token = Cache::get('msbot_token')) {
            return $token;
        }

        $clientId = env('MSBOT_CLIENT_ID');
        $clientSecret = env('MSBOT_CLIENT_SECRET');
        $scope = 'https://graph.microsoft.com/.default';

        $data = sprintf('grant_type=client_credentials&client_id=%s&client_secret=%s&scope=%s', $clientId, $clientSecret, $scope);

        $response = CurlUtils::post(MSBOT_LOGIN_URL, $data);
        $response = json_decode($response);

        $expires = ($response->expires_in / 60) - 5;
        Cache::put('msbot_token', $response->access_token, $expires);

        return $response->access_token;
    }

    private function loadState($token)
    {
        $url = sprintf('%s/botstate/skype/conversations/%s', MSBOT_STATE_URL, '29:1C-OsU7OWBEDOYJhQUsDkYHmycOwOq9QOg5FVTwRX9ts');

        $headers = [
            'Authorization: Bearer ' . $token,
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

        $url = sprintf('%s/%s?subscription-key=%s&verbose=true&q=%s', MSBOT_LUIS_URL, $appId, $subKey, $message);
        //$url = sprintf('%s?id=%s&subscription-key=%s&q=%s', MSBOT_LUIS_URL, $appId, $subKey, $message);
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

        //echo "STATE<pre>" . htmlentities(json_encode($data), JSON_PRETTY_PRINT) . "</pre>";

        $data = '{ eTag: "*", data: "' . addslashes(json_encode($data)) . '" }';

        CurlUtils::post($url, $data, $headers);
    }

    private function sendResponse($token, $to, $message)
    {
        $url = sprintf('%s/conversations/%s/activities/', SKYPE_API_URL, $to);

        $headers = [
            'Authorization: Bearer ' . $token,
        ];

        //echo "<pre>" . htmlentities(json_encode(json_decode($message), JSON_PRETTY_PRINT)) . "</pre>";

        $response = CurlUtils::post($url, $message, $headers);

        //var_dump($response);
    }

    private function validateEmail($email, $botUserId)
    {
        if (! $email || ! $botUserId) {
            return false;
        }

        // delete any expired codes
        SecurityCode::whereBotUserId($botUserId)
                    ->where('created_at', '<', DB::raw('now() - INTERVAL 10 MINUTE'))
                    ->delete();

        if (SecurityCode::whereBotUserId($botUserId)->first()) {
            return false;
        }

        $user = User::whereEmail($email)
                    ->whereNull('bot_user_id')
                    ->first();

        if (! $user) {
            return false;
        }

        $code = new SecurityCode();
        $code->user_id = $user->id;
        $code->account_id = $user->account_id;
        $code->code = mt_rand(100000, 999999);
        $code->bot_user_id = $botUserId;
        $code->save();

        $this->userMailer->sendSecurityCode($user, $code->code);

        return $code->code;
    }

    private function validateCode($input, $botUserId)
    {
        if (! $input || ! $botUserId) {
            return false;
        }

        $code = SecurityCode::whereBotUserId($botUserId)
                    ->where('created_at', '>', DB::raw('now() - INTERVAL 10 MINUTE'))
                    ->where('attempts', '<', 5)
                    ->first();

        if (! $code) {
            return false;
        }

        if (! hash_equals($code->code, $input)) {
            $code->attempts += 1;
            $code->save();

            return false;
        }

        $user = User::find($code->user_id);
        $user->bot_user_id = $code->bot_user_id;
        $user->save();

        return true;
    }

    private function removeBot($botUserId)
    {
        $user = User::whereBotUserId($botUserId)->first();
        $user->bot_user_id = null;
        $user->save();
    }

    private function validateToken($token)
    {
        if (! $token) {
            return false;
        }

        $token = explode(' ', $token)[1];

        // https://blogs.msdn.microsoft.com/tsmatsuz/2016/07/12/developing-skype-bot/
        // 0:Invalid, 1:Valid
        $token_valid = 0;

        // 1 separate token by dot (.)
        $token_arr = explode('.', $token);
        $headers_enc = $token_arr[0];
        $claims_enc = $token_arr[1];
        $sig_enc = $token_arr[2];

        // 2 base 64 url decoding
        $headers_arr = json_decode($this->base64_url_decode($headers_enc), true);
        $claims_arr = json_decode($this->base64_url_decode($claims_enc), true);
        $sig = $this->base64_url_decode($sig_enc);

        // 3 get key list
        $keylist = file_get_contents('https://api.aps.skype.com/v1/keys');
        $keylist_arr = json_decode($keylist, true);
        foreach ($keylist_arr['keys'] as $key => $value) {

            // 4 select one key (which matches)
            if ($value['kid'] == $headers_arr['kid']) {

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
        return $token_valid == 1;
    }

    private function base64_url_decode($arg)
    {
        $res = $arg;
        $res = str_replace('-', '+', $res);
        $res = str_replace('_', '/', $res);
        switch (strlen($res) % 4) {
            case 0:
                break;
            case 2:
                $res .= '==';
                break;
            case 3:
                $res .= '=';
                break;
            default:
                break;
        }
        $res = base64_decode($res);

        return $res;
    }
}

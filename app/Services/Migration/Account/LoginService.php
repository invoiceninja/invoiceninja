<?php

namespace App\Services\Migration\Account;

use Illuminate\Support\Facades\Log;

/**
 * @package App\Services
 */
class LoginService
{
    /**
     * @var array
     */
    private $data;

    public $responseCode;

    public $responseMessage;

    public $successful;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = (object)$data;
    }

    public function login()
    {
        $this->storeInSession();

        if (session('type') == 'hosted') {
            return;
        }

        return $this->loginSelfHosted();
    }

    /**
     * Store needed values into session for future usage.
     * @return void
     */
    private function storeInSession()
    {
        session()->put('email', $this->data->email);

        if (session('type') == 'self_hosted') {
            session()->put([
                'x_api_secret' => $this->data->x_api_secret,
                'self_hosted_url' => $this->data->self_hosted_url
            ]);
        }
    }

    /**
     * @return bool
     */
    private function loginSelfHosted()
    {
        try {

            $headers = [
                'Content-Type' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
                'X-API-SECRET' => $this->data->x_api_secret
            ];

            $credentials = [
                'email' => $this->data->email,
                'password' => $this->data->password,
            ];

            $response = \Unirest\Request::post(
                $this->data->self_hosted_url . '/api/v1/login?include=token',
                $headers,
                json_encode($credentials)
            );

            if ($response->code == 401) {
                $this->responseCode = $response->code;
                $this->responseMessage = $response->body->message;

                $this->successful = false;

                throw new \Exception($response->body->message);

            }

            if ($response->code == 500) {
                $this->successful = false;
                throw new \Exception('Oops something went wrong. Please check the logs or contact our support.');
            }


            if ($response->code == 200) {
                session('x_api_token', $response->body->token->token);

                return $this->successful = true;
            }

        } catch (\Exception $e) {
            $this->successful = false;
            Log::error($e);
        }
    }
}
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

    public $response;

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
     * @return void
     */
    private function loginSelfHosted()
    {
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
            $this->response = $response->body;

            $this->successful = false;
        }

        if ($response->code == 500) {
            $this->successful = false;
        }


        if ($response->code == 200) {
            session('x_api_token', $response->body->data[0]->token->token);
            $this->successful = true;
        }

    }

    public function getSuccessful()
    {
        return $this->successful;
    }
}
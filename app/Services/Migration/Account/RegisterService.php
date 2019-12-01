<?php

namespace App\Services\Migration\Account;

use Illuminate\Support\Facades\Log;
use integration\PhpSpec\Console\Prompter\DialogTest;

/**
 * @package App\Services\Migration\Account
 */
class RegisterService
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var bool
     */
    public $successful;

    /**
     * @var int
     */
    public $responseCode;

    /**
     * @var array
     */
    public $response;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = (object)$data;
    }

    public function register()
    {
        $this->storeInSession();

        if (session('type') == 'hosted') {
            return;
        }

        return $this->registerSelfHosted();
    }

    /**
     * @return void
     */
    public function storeInSession()
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
    public function registerSelfHosted()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => $this->data->x_api_secret
        ];

        $credentials = [
            'first_name' => $this->data->first_name,
            'last_name' => $this->data->last_name,
            'email' => $this->data->email,
            'password' => $this->data->password,
            'terms_of_service' => 1,
            'privacy_policy' => 1,
        ];

        $response = \Unirest\Request::post(
            $this->data->self_hosted_url . '/api/v1/signup?include=token',
            $headers,
            json_encode($credentials)
        );

        if ($response->code == 401 || $response->code == 422) {
            $this->responseCode = $response->code;
            $this->response = $response->body;
            $this->successful = false;
        }

        if ($response->code == 500) {
            $this->successful = false;
        }

        if ($response->code == 200) {
            session('x_api_token', $response->body->token->token);
            $this->successful = true;
        }
    }

    /**
     * @return mixed
     */
    public function getSuccessful()
    {
        return $this->successful;
    }
}
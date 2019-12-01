<?php

namespace App\Services\Migration\Account;

use Illuminate\Support\Facades\Log;

/**
 * @package App\Services\Migration\Account
 */
class RegisterService
{
    /**
     * @var array
     */
    private $data;

    public $successful;

    public $responseCode;

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

    public function registerSelfHosted()
    {
        try {
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
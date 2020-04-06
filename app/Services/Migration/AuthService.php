<?php

namespace App\Services\Migration;

use Unirest\Request;
use Unirest\Request\Body;

class AuthService
{
    protected $username;
    protected $password;
    protected $endpoint = 'https://app.invoiceninja.com';
    protected $uri = '/api/v1/login?include=token';
    protected $errors = [];
    protected $token;
    protected $isSuccessful;


    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function endpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function start()
    {
        $data = [
            'email' => $this->username,
            'password' => $this->password,
        ];

        $body = Body::json($data);

        $response = Request::post($this->getUrl(), $this->getHeaders(), $body);

        if ($response->code == 200) {
            
            try {
                $this->isSuccessful = true;
                $this->token = $response->body->data[0]->token->token;
            } catch (\Exception $e) {
                info($e->getMessage());

                $this->isSuccessful = false;
                $this->errors = [trans('texts.migration_went_wrong')];
            }
        }

        if (in_array($response->code, [401, 422, 500])) {
            $this->isSuccessful = false;
            $this->processErrors($response->body);
        }

        return $this;
    }

    public function isSuccessful()
    {
        return $this->isSuccessful;
    }

    public function getAccountToken()
    {
        if ($this->isSuccessful) {
            return $this->token;
        }

        return null;
    }


    public function getErrors()
    {
        return $this->errors;
    }

    private function getHeaders()
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
        ];
    }

    private function getUrl()
    {
        return $this->endpoint . $this->uri;
    }

    private function processErrors($errors)
    {
        $array = (array) $errors;

        $this->errors = $array;
    }
}

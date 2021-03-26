<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */


namespace App\Services\Migration;

use Unirest\Request;
use Unirest\Request\Body;

class AuthService
{
    protected $username;
    protected $password;
    protected $apiSecret;

    protected $endpoint = 'https://app.invoiceninja.com';
    protected $uri = '/api/v1/login?include=token';

    protected $errors = [];
    protected $token;
    protected $isSuccessful;


    public function __construct(string $username, string $password, string $apiSecret = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->apiSecret = $apiSecret;
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

        if (in_array($response->code, [401])) {
            info($response->raw_body);

            $this->isSuccessful = false;
            $this->processErrors($response->body->message);
        } elseif (in_array($response->code, [200])) {
            $this->isSuccessful = true;
            $this->token = $response->body->data[0]->token->token;
        } else {
            info($response->raw_body);

            $this->isSuccessful = false;
            $this->errors = [trans('texts.migration_went_wrong')];
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

    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    private function getHeaders()
    {
        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
        ];

        if (!is_null($this->apiSecret)) {
            $headers['X-Api-Secret'] = $this->apiSecret;
        }

        return $headers;
    }

    private function getUrl()
    {
        return $this->endpoint . $this->uri;
    }

    private function processErrors($errors)
    {
        $array = (array)$errors;

        $this->errors = $array;
    }
}

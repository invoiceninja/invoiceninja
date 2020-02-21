<?php

namespace App\Services\Migration;

use Unirest\Request;
use Unirest\Request\Body;

class CompleteService
{
    protected $token;
    protected $companies = [];
    protected $file;
    protected $endpoint = 'https://app.invoiceninja.com';
    protected $uri = '/api/v1/migration/start';
    protected $errors = [];
    protected $isSuccessful;


    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function file($file)
    {
        $this->file = $file;

        return $this;
    }

    public function companies($companies)
    {
        $this->companies = $companies;

        return $this;
    }

    public function endpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function start()
    {
        $response = Request::post($this->getUrl(), $this->getHeaders());

        // ..

        return $this;
    }

    public function isSuccessful()
    {
        return $this->isSuccessful;
    }


    public function getErrors()
    {
        return $this->errors;
    }

    private function getHeaders()
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Api-Token' => $this->token,
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

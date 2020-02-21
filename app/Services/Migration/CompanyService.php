<?php

namespace App\Services\Migration;

use Unirest\Request;
use Unirest\Request\Body;

class CompanyService
{
    protected $token;
    protected $endpoint = 'https://app.invoiceninja.com';
    protected $uri = '/api/v1/companies';
    protected $errors = [];
    protected $isSuccessful;
    protected $companies = [];


    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function endpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function start()
    {
        $response = Request::get($this->getUrl(), $this->getHeaders());

        if ($response->code == 200) {
            $this->isSuccessful = true;

            foreach($response->body->data as $company) {
                $this->companies[] = $company;
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

    public function getCompanies()
    {
        if ($this->isSuccessful) {
            return $this->companies;
        }

        return [];
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

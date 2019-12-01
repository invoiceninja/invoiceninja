<?php

namespace App\Services\Migration;

/**
 * @package App\Services\Migration
 */
class CompanyService
{
    private $successful;
    private $response;
    private $responseCode;
    private $companies;

    public function __construct()
    {
        // ..
    }

    public function getCompanies()
    {
        $this->requestCompanies();

        return $this->companies;
    }

    private function requestCompanies()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => session('x_api_secret'),
            'X-API-TOKEN' => session('x_api_token'),
        ];

        dd(session()->all());

        $response = \Unirest\Request::get(
            session('self_hosted_url') . '/api/v1/companies',
            $headers,
            []
        );

        $this->responseCode = $response->code;

        if($this->responseCode == 401) {
            $this->successful = false;
        }

        if($this->responseCode == 200) {
            $this->successful = true;
            $this->companies = $response->data;
        }
    }
}
<?php

namespace App\Services\Migration;

/**
 * @package App\Services\Migration
 */
class CompaniesService
{
    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    private $api_endpoint;

    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    private $api_secret;

    /**
     * @var \Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed
     */
    private $api_token;

    /**
     * @var array
     */
    private $companies;

    public function __construct()
    {
        $this->api_endpoint = session('api-endpoint');
        $this->api_secret = session('x-api-secret');
        $this->api_token = session('api-token');
        $this->companies = [];
    }

    /**
     * @return void
     */
    public function get()
    {
        try {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->api_endpoint . '/api/v1/companies');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $headers = [];
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Api-Secret: ' . $this->api_secret;
            $headers[] = 'X-Api-Token: ' . $this->api_token;
            $headers[] = 'X-Requested-With: XMLHttpRequest';

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = json_decode(curl_exec($ch));

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            curl_close($ch);

            return $this->companies = $result->data;

        } catch (\Exception $exception) {
            return info($exception);
        }
    }

    /**
     * @return mixed
     */
    public function getCompanies()
    {
        return $this->companies;
    }
}

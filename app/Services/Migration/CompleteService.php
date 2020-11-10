<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Storage;
use Unirest\Request;
use Unirest\Request\Body;

class CompleteService
{
    protected $token;

    protected $endpoint = 'https://app.invoiceninja.com';

    protected $uri = '/api/v1/migration/start/';

    protected $errors = [];

    protected $isSuccessful;

    protected $data;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function data(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function endpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function start()
    {
        $body = [
            'companies' => [],
        ];

        foreach ($this->data as $companyKey => $companyData) {
            $body['companies'][] = [
                'company_key' => $companyKey,
                'migration' => \Unirest\Request\Body::file($companyData['file'], 'application/zip'),
                'force' => $companyData['force'],
            ];
        }

        try {
            $response = Request::post($this->getUrl(), $this->getHeaders(), json_encode($body));

            dd($response);
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

        if ($response->code == 200) {
            $this->isSuccessful = true;
        }

        if (in_array($response->code, [401, 422, 500])) {
            $this->isSuccessful = false;
            $this->errors = [
                'Oops, something went wrong. Migration can\'t be processed at the moment.',
            ];
        }

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
            'Content-Type' => 'multipart/form-data',
        ];
    }

    private function getUrl()
    {
        return "{$this->endpoint}/{$this->uri}";
    }

    public function deleteFile(string $path)
    {
        Storage::delete($path);
    }
}

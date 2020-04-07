<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Storage;
use Unirest\Request;
use Unirest\Request\Body;

class CompleteService
{
    protected $token;
    protected $company;
    protected $file;
    protected $endpoint = 'https://app.invoiceninja.com';
    protected $uri = '/api/v1/migration/start/';
    protected $errors = [];
    protected $isSuccessful;
    protected $force = false;
    protected $companyKey;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function file($file)
    {
        $this->file = $file;

        return $this;
    }

    public function force($option)
    {
        $this->force = $option;

        return $this;
    }

    public function company($company)
    {
        $this->company = $company;

        return $this;
    }

    public function endpoint(string $endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function companyKey(string $key)
    {
        $this->companyKey = $key;

        return $this;
    }

    public function start()
    {
        $body = [
            'migration' => \Unirest\Request\Body::file($this->file, 'application/zip'),
            'force' => $this->force,
            'company_key' => $this->companyKey,
        ];

        $response = Request::post($this->getUrl(), $this->getHeaders(), $body);

        if ($response->code == 200) {
            $this->isSuccessful = true;
            $this->deleteFile();
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
        return $this->endpoint . $this->uri . $this->company;
    }

    public function deleteFile()
    {
        Storage::delete($this->file);
    }
}

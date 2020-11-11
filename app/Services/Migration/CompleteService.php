<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Storage;
use Unirest\Request;

class CompleteService
{
    protected $token;

    protected $endpoint = 'https://app.invoiceninja.com';

    protected $uri = 'api/v1/migration/start';

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
        $files = [];

        foreach ($this->data as $companyKey => $companyData) {
            $data[] = [
                'company_key' => $companyKey,
                'force' => $companyData['force'],
            ];

            $files[$companyKey] = $companyData['file'];
        }

        $body = \Unirest\Request\Body::multipart(['companies' => json_encode($data)], $files);

        try {
            $response = Request::post($this->getUrl(), $this->getHeaders(), $body);
        } catch (\Exception $e) {
            info($e->getMessage());
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

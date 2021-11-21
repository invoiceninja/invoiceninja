<?php

namespace App\Services\Migration;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Storage;
// use Unirest\Request;

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

            $data = [
                'company_index' => $companyKey,
                'company_key' => $companyData['data']['company']['company_key'],
                'force' => $companyData['force'],
                'contents' => 'name',
                'name' => $companyKey, 
            ];

            $payload[$companyKey] = [
                'contents' => json_encode($data),
                'name' => $companyData['data']['company']['company_key'],
            ];

            $files[] = [
                'name' => $companyKey, 
                'company_index' => $companyKey,
                'company_key' => $companyData['data']['company']['company_key'],
                'force' => $companyData['force'],
                'contents' => file_get_contents($companyData['file']),
                'filename' => basename($companyData['file']),
                'Content-Type' => 'application/zip'
            ];
        }

        $client =  new \GuzzleHttp\Client(
        [
            'headers' => $this->getHeaders(),
        ]);

        $payload_data = [
                'multipart'=> array_merge($files, $payload),
             ];

        // info(print_r($payload_data,1));
        $response = $client->request("POST", $this->getUrl(),$payload_data);

        if($response->getStatusCode() == 200){

            $this->isSuccessful = true;
            return json_decode($response->getBody(),true);
        }else {
            // info($response->raw_body);

            $this->isSuccessful = false;
            $this->errors = [
                'Oops, something went wrong. Migration can\'t be processed at the moment. Please checks the logs.',
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
        $headers =  [
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Api-Token' => $this->token,
            'Content-Type' => 'multipart/form-data',
        ];

        if (session('MIGRATION_API_SECRET')) {
            $headers['X-Api-Secret'] = session('MIGRATION_API_SECRET');
        }

        return $headers;
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

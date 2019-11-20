<?php

namespace App\Services\Migration;

/**
 * @package App\Services\Migration
 */
class PurgeService
{
    /**
     * @var string
     */
    private $option;

    /**
     * @var array
     */
    private $companies;

    /**
     * @param string $option
     * @param array $companies
     */
    public function __construct(string $option, array $companies)
    {
        $this->option = $option;
        $this->companies = $companies;
    }

    /**
     * @throws \Exception
     */
    public function handle()
    {
        /** For testing we'll take only first company. */

        try {
            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->url(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/json",
                    "x-api-password: " . decrypt(session('password')),
                    "x-api-secret: " . session('x-api-secret'),
                    "x-api-token: " . session('api-token'),
                    "x-requested-with: XMLHttpRequest"
                ),
            ));

            $response = curl_exec($curl);
            $error = curl_error($curl);

            curl_close($curl);

            if ($error) {
                throw new \Exception($error);
            }

            dd($response);

        } catch (\Exception $e) {
            info($e);
            dd($e);
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function url()
    {
        $options = [
            'purge_without_settings',
            'purge_with_settings',
        ];

        $route = 'purge';

        if (!in_array($this->option, $options)) {
            throw new \Exception('Purge option not available.');
        }

        if ($this->option == 'purge_with_settings') {
            $route = 'purge_save_settings';
        }

        return sprintf("%s/api/v1/migration/%s/%s", session('api-endpoint'), $route, $this->companies[0]);
    }
}

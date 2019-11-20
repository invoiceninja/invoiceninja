<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Log;

/**
 * @package App\Services\Migration
 */
class LoginService
{
    private $email_address;
    private $password;
    private $x_api_secret;
    private $endpoint;

    /**
     * Shows a final result, was authentication successful.
     *
     * @var bool
     */
    private $was_successful;

    /**
     * Complete response from the V2 endpoint.
     *
     * @var array
     */
    public $response;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->email_address = $data['email_address'];
        $this->password = $data['password'];
        $this->x_api_secret = $data['x_api_secret'];

        // TODO: Check if application is hosted.
        $this->endpoint = $data['api_endpoint'];
    }

    public function handle(): bool
    {
        try {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->endpoint . '/api/v1/login?include=token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Api-Secret: ' . $this->x_api_secret;
            $headers[] = 'X-Requested-With: XMLHttpRequest';

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->payload()));

            $result = json_decode(curl_exec($ch));

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 401) {
                return $this->was_successful = false;
            }

            /** Store API key to use in future. */
            session(['api-token' => $result->data[0]->token->token]);

            return $this->was_successful = true;
        } catch (\Exception $e) {
            info($e);
            return $this->was_successful = false;
        }
    }

    public function wasSuccessful(): bool
    {
        if ($this->was_successful) {

            /**
             * Store items into session, so we can use them later, without
             * asking user to enter them again.
             */
            session([
                'email-address' => $this->email_address,
                'x-api-secret' => $this->x_api_secret,
                'api-endpoint' => $this->endpoint,
            ]);

            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    private function payload(): array
    {
        return [
            'email' => $this->email_address,
            'password' => $this->password,
        ];
    }

}

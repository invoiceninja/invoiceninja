<?php


namespace App\Services\Migration;

/**
 * @package App\Services\Migration
 */
class RegisterService
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var bool
     */
    private $was_successful;

    /**
     * @var array
     */
    public $response;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->was_successful = false;
        $this->response = [];
    }

    public function handle()
    {
        try {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->data['api_endpoint'] . '/api/v1/signup?include=token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->payload()));

            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'X-Api-Secret: ' . $this->data['x_api_secret'];
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $result = json_decode(curl_exec($ch));

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 422) {
                $this->response = $result;

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

    private function payload()
    {
        return [
            'email' => $this->data['email_address'],
            'password' => $this->data['password'],
            'first_name' => $this->data['first_name'],
            'last_name' => $this->data['last_name'],
            'terms_of_service' => $this->data['tos'],
            'privacy_policy' => $this->data['privacy_policy'],
        ];
    }

    public function wasSuccessful(): bool
    {
        if ($this->was_successful) {

            /**
             * Store items into session, so we can use them later, without
             * asking user to enter them again.
             */
            session([
                'x-api-secret' => $this->data['x_api_secret'],
                'api-endpoint' => $this->data['api_endpoint'],
            ]);

            return true;
        }

        return false;
    }
}

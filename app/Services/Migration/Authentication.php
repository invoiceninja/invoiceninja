<?php

namespace App\Services\Migration;

/**
 * @package App\Services\Migration
 */
class Authentication
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
    private $response;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->email_address = $data['email_address'];
        $this->password = $data['password'];
        $this->x_api_secret = $data['x_api_secret'];

        // TODO: Check if application is self-hosted.
        $this->endpoint = $data['api_endpoint'];
    }

    public function handle()
    {
        $this->was_successful = true;
    }

    public function wasSuccessful()
    {
        if ($this->was_successful) {
            return true;
        }

        return false;
    }
}
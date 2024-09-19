<?php

namespace App\Exceptions;

use Exception;

class CustomApiException extends Exception
{
    protected $statusCode;
    protected $errorCode;

    public function __construct($message = null, $statusCode = 400, $errorCode = 'CUSTOM_ERROR')
    {
        parent::__construct($message);

        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }
}
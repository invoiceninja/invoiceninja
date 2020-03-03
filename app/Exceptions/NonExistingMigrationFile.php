<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class NonExistingMigrationFile extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function report()
    {
        return 'Migration file doesn\'t exist or it is corrupted.';
    }
}

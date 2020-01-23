<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ProcessingMigrationArchiveFailed extends Exception
{
    /**
     * @var Throwable
     */
    private $previous;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->message = $message;
        $this->code = $code;
        $this->previous = $previous;
    }

    public function report()
    {
        return 'Unable to open migration archive.';
    }

}

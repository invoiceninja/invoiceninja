<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ResourceNotAvailableForMigration extends Exception
{
    private $resource;

    public function __construct($resource)
    {
        parent::__construct();

        $this->resource = $resource;
    }

    public function report()
    {
        // TODO: Handle this nicely, throw response, etc.
        return "Resource {$this->resource} is not available for the migration.";
    }

}

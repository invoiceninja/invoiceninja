<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ResourceDependencyMissing extends Exception
{
    private $resource;
    private $dependency;

    public function __construct($resource, $dependency)
    {
        parent::__construct();
        $this->resource = $resource;
        $this->dependency = $dependency;
    }

    public function report()
    {
        return "Resource '{$this->resource}' depends on '{$this->dependency}'.";
    }
}

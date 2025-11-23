<?php

namespace Tests;

define('LARAVEL_START', microtime(true));

use Mockery;
use App\Utils\Traits\AppSetup;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use AppSetup;

    protected function setUp(): void
    {
        
        parent::setUp();
    }

}

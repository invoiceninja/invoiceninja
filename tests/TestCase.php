<?php

namespace Tests\Unit;

use Codeception\TestCase\Test;

abstract class TestCase extends Test
{
    protected function createApplication()
    {
        $app = require __DIR__ .'/../../../bootstrap/app.php';
        $app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

        return $app;
    }

}

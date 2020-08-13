<?php

namespace Tests\Feature\PdfMaker;

use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;

class ExampleDesign
{
    use DesignHelpers;

    public $client;

    public $entity;

    public $context;

    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/example-design.html')
        );
    }
}
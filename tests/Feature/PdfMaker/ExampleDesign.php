<?php

namespace Tests\Feature\PdfMaker;

class ExampleDesign
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/example-design.html')
        );
    }
}
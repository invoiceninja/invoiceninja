<?php

namespace Tests\Feature\PdfMaker;

class Hipster
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/hipster.html')
        );
    }
}

<?php

namespace Tests\Feature\PdfMaker;

class Elegant
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/elegant.html')
        );
    }
}

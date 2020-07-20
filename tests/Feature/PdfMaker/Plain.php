<?php

namespace Tests\Feature\PdfMaker;

class Plain
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/plain.html')
        );
    }
}

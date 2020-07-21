<?php

namespace Tests\Feature\PdfMaker;

class creative
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/creative.html')
        );
    }
}

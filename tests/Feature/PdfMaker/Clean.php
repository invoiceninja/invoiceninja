<?php

namespace Tests\Feature\PdfMaker;

class Clean
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/clean.html')
        );
    }
}

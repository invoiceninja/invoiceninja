<?php

namespace Tests\Feature\PdfMaker;

class Modern
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/modern.html')
        );
    }
}

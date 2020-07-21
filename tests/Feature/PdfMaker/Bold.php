<?php

namespace Tests\Feature\PdfMaker;

class Bold
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/bold.html')
        );
    }
}

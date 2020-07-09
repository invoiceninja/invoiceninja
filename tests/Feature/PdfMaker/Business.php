<?php

namespace Tests\Feature\PdfMaker;

class Business
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/business.html')
        );
    }
}

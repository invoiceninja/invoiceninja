<?php

namespace Tests\Feature\PdfMaker;

class Playful
{
    public function html()
    {
        return file_get_contents(
            base_path('tests/Feature/PdfMaker/designs/playful.html')
        );
    }
}

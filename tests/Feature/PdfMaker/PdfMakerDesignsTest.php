<?php

namespace Tests\Feature\PdfMaker;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PdfMakerDesignsTest extends TestCase
{
    public $state = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->state = [
            'variables' => [
                '$css' => asset('css/tailwindcss@1.4.6.css'),
            ],
        ];
    }

    public function testBusiness()
    {
        $state = [
            'template' => [],
            'variables' => $this->state['variables'],
        ];
    }
}

<?php

namespace Tests\Pdf;

use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

/**
 * @test
 //@covers  App\DataMapper\BaseSettings
 */
class PdfGenerationTest extends TestCase
{
	
    public function setUp() :void
    {
    
    parent::setUp();
	
	}


	public function testPdfGeneration()
	{
		$html = file_get_contents(base_path().'/tests/Pdf/invoice.html');
		$pdf = base_path().'/tests/Pdf/invoice.pdf';

		Browsershot::html($html)->save($pdf);

		$this->assertTrue(file_exists($pdf));

		unlink($pdf);

	}
}

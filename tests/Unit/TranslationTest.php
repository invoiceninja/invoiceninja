<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * @test
 */
class TranslationTest extends TestCase
{
    public function setUp() :void
    {
        parent::setUp();
    }

    public function testAddTranslation()
    {
        Lang::set('texts.test_translation_string', 'test');

        $this->assertEquals('test', trans('texts.test_translation_string'));
    }

    public function testReplaceTranslation()
    {
        Lang::set('texts.invoice_number', 'test');

        $this->assertEquals('test', trans('texts.invoice_number'));
    }

    public function testReplaceArray()
    {
        $data = [
            'texts.invoice_number' => 'test',
            'texts.custom_translation' => 'test2',
        ];

        Lang::replace($data);

        $this->assertEquals('test', trans('texts.invoice_number'));
        $this->assertEquals('test2', trans('texts.custom_translation'));
    }
}

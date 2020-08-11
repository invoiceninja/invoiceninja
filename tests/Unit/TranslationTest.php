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



}

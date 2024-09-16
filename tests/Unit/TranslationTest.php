<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * 
 */
class TranslationTest extends TestCase
{
    protected function setUp(): void
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

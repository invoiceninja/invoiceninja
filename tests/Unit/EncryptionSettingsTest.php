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

use Tests\TestCase;

/**
 * @test
 */
class EncryptionSettingsTest extends TestCase
{
    protected function setUp() :void
    {
        parent::setUp();

        $this->settings = '{"publishable_key":"publish","23_apiKey":"client","enable_ach":"1","enable_sofort":"1","enable_apple_pay":"1","enable_alipay":"1"}';
    }

    public function testDecryption()
    {
        $this->settings = encrypt($this->settings);

        $this->assertEquals('publish', $this->getConfigField('publishable_key'));
        $this->assertEquals('client', $this->getConfigField('23_apiKey'));
        $this->assertEquals(1, $this->getConfigField('enable_ach'));
        $this->assertEquals(1, $this->getConfigField('enable_sofort'));
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return json_decode(decrypt($this->settings));
    }

    /**
     * @param $field
     *
     * @return mixed
     */
    public function getConfigField($field)
    {
        return object_get($this->getConfig(), $field, false);
    }
}

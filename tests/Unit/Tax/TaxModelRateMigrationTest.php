<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Tax;

use App\DataMapper\Tax\TaxModel;
use Tests\TestCase;

class TaxModelRateMigrationTest extends TestCase
{
    public function testFreshModelUsesCurrentStatutoryRates(): void
    {
        $tax_model = new TaxModel();

        $this->assertEquals('theta', $tax_model->version);
        $this->assertCurrentRates($tax_model);
        $this->assertEquals(9, $tax_model->regions->EU->subregions->EE->reduced_tax_rate);
        $this->assertEquals(19, $tax_model->regions->EU->subregions->DE->tax_rate);
        $this->assertEquals(7, $tax_model->regions->EU->subregions->DE->reduced_tax_rate);
        $this->assertEquals(20, $tax_model->regions->UK->subregions->GB->tax_rate);
        $this->assertEquals(5, $tax_model->regions->UK->subregions->GB->reduced_tax_rate);
    }

    public function testEtaModelAdvancesToCurrentRatesWithoutReplacingSubregions(): void
    {
        $regions = (new TaxModel())->init();
        $regions->EU->subregions->EE->tax_rate = 20;
        $regions->EU->subregions->EE->reduced_tax_rate = 9;
        $regions->EU->subregions->EE->vat_number = 'EE123456789';
        $regions->EU->subregions->EE->apply_tax = true;
        $regions->EU->subregions->EE->tax_name = 'KM';

        $regions->EU->subregions->FI->tax_rate = 24;
        $regions->EU->subregions->FI->reduced_tax_rate = 14;
        $regions->EU->subregions->SK->tax_rate = 20;
        $regions->EU->subregions->SK->reduced_tax_rate = 10;
        $regions->EU->subregions->RO->tax_rate = 19;
        $regions->EU->subregions->RO->reduced_tax_rate = 5;
        $regions->EU->subregions->CZ->reduced_tax_rate = 15;
        $regions->EU->subregions->LT->reduced_tax_rate = 9;
        $regions->EU->subregions->BG->reduced_tax_rate = 5;
        $regions->EU->subregions->BG->vat_number = 'BG123456789';
        $regions->EU->subregions->IE->reduced_tax_rate = 0;
        $regions->EU->subregions->LI->tax_rate = 7.7;
        $regions->EU->subregions->LI->reduced_tax_rate = 2.5;

        $stored = new \stdClass();
        $stored->seller_subregion = 'DE';
        $stored->version = 'eta';
        $stored->acts_as_sender = true;
        $stored->acts_as_receiver = false;
        $stored->regions = $regions;

        $tax_model = new TaxModel($stored);

        $this->assertEquals('theta', $tax_model->version);
        $this->assertTrue($tax_model->acts_as_sender);
        $this->assertFalse($tax_model->acts_as_receiver);
        $this->assertCurrentRates($tax_model);
        $this->assertEquals(9, $tax_model->regions->EU->subregions->EE->reduced_tax_rate);
        $this->assertEquals('KM', $tax_model->regions->EU->subregions->EE->tax_name);
        $this->assertEquals('EE123456789', $tax_model->regions->EU->subregions->EE->vat_number);
        $this->assertTrue($tax_model->regions->EU->subregions->EE->apply_tax);
        $this->assertEquals('BG123456789', $tax_model->regions->EU->subregions->BG->vat_number);
        $this->assertEquals('ДДС', $tax_model->regions->EU->subregions->BG->tax_name);
    }

    public function testThetaModelIsLeftUnchanged(): void
    {
        $current = new TaxModel();
        $again = new TaxModel(json_decode(json_encode($current)));

        $this->assertEquals('theta', $again->version);
        $this->assertCurrentRates($again);
        $this->assertEquals(9, $again->regions->EU->subregions->EE->reduced_tax_rate);
    }

    private function assertCurrentRates(TaxModel $tax_model): void
    {
        $eu = $tax_model->regions->EU->subregions;

        $this->assertEqualsWithDelta(24, $eu->EE->tax_rate, 0.001);
        $this->assertEqualsWithDelta(25.5, $eu->FI->tax_rate, 0.001);
        $this->assertEqualsWithDelta(13.5, $eu->FI->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(23, $eu->SK->tax_rate, 0.001);
        $this->assertEqualsWithDelta(5, $eu->SK->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(21, $eu->RO->tax_rate, 0.001);
        $this->assertEqualsWithDelta(11, $eu->RO->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(21, $eu->CZ->tax_rate, 0.001);
        $this->assertEqualsWithDelta(12, $eu->CZ->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(21, $eu->LT->tax_rate, 0.001);
        $this->assertEqualsWithDelta(12, $eu->LT->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(20, $eu->BG->tax_rate, 0.001);
        $this->assertEqualsWithDelta(9, $eu->BG->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(23, $eu->IE->tax_rate, 0.001);
        $this->assertEqualsWithDelta(13.5, $eu->IE->reduced_tax_rate, 0.001);
        $this->assertEqualsWithDelta(8.1, $eu->LI->tax_rate, 0.001);
        $this->assertEqualsWithDelta(2.6, $eu->LI->reduced_tax_rate, 0.001);
    }
}

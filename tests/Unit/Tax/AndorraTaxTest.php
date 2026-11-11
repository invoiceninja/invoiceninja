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

class AndorraTaxTest extends TestCase
{
    public function testAndorraRegionExists(): void
    {
        $tax_model = new TaxModel();

        $this->assertTrue(property_exists($tax_model->regions, 'AD'));
    }

    public function testAndorraRegionHasCorrectStructure(): void
    {
        $tax_model = new TaxModel();

        $ad = $tax_model->regions->AD;

        $this->assertFalse($ad->has_sales_above_threshold);
        $this->assertFalse($ad->tax_all_subregions);
        $this->assertEquals(40000, $ad->tax_threshold);
        $this->assertTrue(property_exists($ad, 'subregions'));
    }

    public function testAndorraSubregionHasCorrectTaxRates(): void
    {
        $tax_model = new TaxModel();

        $ad = $tax_model->regions->AD->subregions->AD;

        $this->assertEquals(4.5, $ad->tax_rate);
        $this->assertEquals('IGI', $ad->tax_name);
        $this->assertEquals(1, $ad->reduced_tax_rate);
        $this->assertFalse($ad->apply_tax);
    }

    public function testAndorraIsNotUnderEuRegion(): void
    {
        $tax_model = new TaxModel();

        $this->assertFalse(property_exists($tax_model->regions->EU->subregions, 'AD'));
    }

    public function testAndorraMigrationFromEpsilon(): void
    {
        $model = new \stdClass();
        $model->seller_subregion = 'AD';
        $model->version = 'epsilon';
        $model->acts_as_sender = false;
        $model->acts_as_receiver = false;
        $model->regions = (new TaxModel())->init();

        $tax_model = new TaxModel($model);

        $this->assertTrue(property_exists($tax_model->regions, 'AD'));
        $this->assertEquals('eta', $tax_model->version);
        $this->assertEquals(4.5, $tax_model->regions->AD->subregions->AD->tax_rate);
    }
}

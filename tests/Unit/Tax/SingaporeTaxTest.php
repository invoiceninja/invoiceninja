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

class SingaporeTaxTest extends TestCase
{
    public function testSingaporeRegionExists(): void
    {
        $tax_model = new TaxModel();

        $this->assertTrue(property_exists($tax_model->regions, 'SG'));
    }

    public function testSingaporeRegionHasCorrectStructure(): void
    {
        $tax_model = new TaxModel();

        $sg = $tax_model->regions->SG;

        $this->assertFalse($sg->has_sales_above_threshold);
        $this->assertFalse($sg->tax_all_subregions);
        $this->assertEquals(1000000, $sg->tax_threshold);
        $this->assertTrue(property_exists($sg, 'subregions'));
    }

    public function testSingaporeSubregionHasCorrectTaxRates(): void
    {
        $tax_model = new TaxModel();

        $sg = $tax_model->regions->SG->subregions->SG;

        $this->assertEquals(9, $sg->tax_rate);
        $this->assertEquals('GST', $sg->tax_name);
        $this->assertEquals(0, $sg->reduced_tax_rate);
        $this->assertFalse($sg->apply_tax);
    }

    public function testSingaporeIsNotUnderEuRegion(): void
    {
        $tax_model = new TaxModel();

        $this->assertFalse(property_exists($tax_model->regions->EU->subregions, 'SG'));
    }

    public function testSingaporeMigrationFromZeta(): void
    {
        $model = new \stdClass();
        $model->seller_subregion = 'SG';
        $model->version = 'zeta';
        $model->acts_as_sender = false;
        $model->acts_as_receiver = false;
        $model->regions = (new TaxModel())->init();

        $tax_model = new TaxModel($model);

        $this->assertTrue(property_exists($tax_model->regions, 'SG'));
        $this->assertEquals('eta', $tax_model->version);
        $this->assertEquals(9, $tax_model->regions->SG->subregions->SG->tax_rate);
    }
}

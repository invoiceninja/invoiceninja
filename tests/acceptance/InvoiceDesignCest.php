<?php

use Faker\Factory;

class InvoiceDesignCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function _after(AcceptanceTester $I)
    {

    }

    // tests
    public function updateInvoiceDesign(AcceptanceTester $I)
    {
        $I->wantTo('Design my invoice');

        $I->amOnPage('/settings/invoice_design');

        $I->click('select#invoice_design_id');
        $I->click('select#invoice_design_id option:nth-child(2)');

        $I->fillField('#font_size', 10);

        $I->click('#primary_color + .sp-replacer');
        $I->executeJS('$("#primary_color").val("#7364b6")');
        $I->executeJS('$(".sp-container:nth-child(1) .sp-choose").click()');

        $I->click('#secondary_color + .sp-replacer');
        $I->executeJS('$("#secondary_color").val("#aa6709")');
        $I->executeJS('$(".sp-container:nth-child(2) .sp-choose").click()');

        /*
        $I->fillField(['name' => 'labels_item'], $this->faker->text(6));
        $I->fillField(['name' => 'labels_description'], $this->faker->text(12));
        $I->fillField(['name' => 'labels_unit_cost'], $this->faker->text(12));
        $I->fillField(['name' => 'labels_quantity'], $this->faker->text(8));

        $I->uncheckOption('#hide_quantity');
        $I->checkOption('#hide_paid_to_date');
        */
        
        $I->click('Save');
        $I->wait(3);

        $I->seeInDatabase('accounts', ['font_size' => 10]);
    }
}
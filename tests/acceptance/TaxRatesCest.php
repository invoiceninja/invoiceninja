/<?php

use Codeception\Util\Fixtures;
use Faker\Factory;

class TaxRatesCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function taxRates(AcceptanceTester $I)
    {
        $I->wantTo('test tax rates');

        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);
        $itemTaxRate = $this->faker->randomFloat(2, 5, 15);
        $itemTaxName = $this->faker->word();
        $invoiceTaxRate = $this->faker->randomFloat(2, 5, 15);
        $invoiceTaxName = $this->faker->word();
        $itemCost = $this->faker->numberBetween(1, 20);

        $total = $itemCost;
        $total += round($itemCost * $itemTaxRate / 100, 2);
        $total += round($itemCost * $invoiceTaxRate / 100, 2);

        $itemTaxRate = number_format($itemTaxRate, 3);
        $invoiceTaxRate = number_format($invoiceTaxRate, 3);

        // create tax rates
        $I->amOnPage('/tax_rates/create');
        $I->fillField(['name' => 'name'], $itemTaxName);
        $I->fillField(['name' => 'rate'], $itemTaxRate);
        $I->click('Save');
        $I->see($itemTaxName);

        $I->amOnPage('/tax_rates/create');
        $I->fillField(['name' => 'name'], $invoiceTaxName);
        $I->fillField(['name' => 'rate'], $invoiceTaxRate);
        $I->click('Save');
        $I->see($invoiceTaxName);

        // enable line item taxes
        $I->amOnPage('/settings/tax_rates');
        $I->checkOption('#invoice_item_taxes');
        $I->click('Save');

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $itemCost);
        $I->selectOption('select[name=default_tax_rate_id]', $itemTaxName . ' ' . $itemTaxRate . '%');
        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        // create invoice
        $I->amOnPage('/invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->selectOption('#taxRateSelect1', $invoiceTaxName . ' ' . floatval($invoiceTaxRate) . '%');
        $I->wait(3);

        // check total is right before saving
        $I->see("\${$total}");
        $I->click('Save');
        $I->wait(3);
        $I->see($clientEmail);

        // check total is right after saving
        $I->see("\${$total}");
        $I->amOnPage('/invoices');
        $I->wait(2);

        // check total is right in list view
        $I->see("\${$total}");
   }

}

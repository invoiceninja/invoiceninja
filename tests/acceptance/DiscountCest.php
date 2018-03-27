<?php

use Codeception\Util\Fixtures;
use Faker\Factory;

class DiscountCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function lineItemDiscounts(AcceptanceTester $I)
    {
        $I->wantTo('test line item discounts');

        // enable line item taxes
        $I->amOnPage('/settings/tax_rates');
        $I->checkOption('#invoice_item_taxes');
        $I->click('Save');

        $clientEmail = $this->faker->safeEmail;
        $itemTaxName = $this->faker->word;
        $productKey = $this->faker->word;
        $itemTaxRate = rand(1, 1000) / 10;
        $itemDiscount = rand(1, 1000) / 10;
        $itemDiscount = rand(1, 1000) / 10;
        $discount = rand(1, 1000) / 10;
        $itemAmount = rand(1, 10000) / 10;
        $quantity = rand(1,20);

        $I->amOnPage('/settings/invoice_design#product_fields');
        $I->selectOption('#product_fields_select', 'product.discount');
        $I->click('Save');

        $I->amOnPage('/clients/create');

        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $clientId = $I->grabFromCurrentUrl('~clients/(\d+)~');

        $I->amOnPage('/tax_rates/create');
        $I->fillField(['name' => 'name'], $itemTaxName);
        $I->fillField(['name' => 'rate'], $itemTaxRate);
        $I->click('Save');
        $I->see($itemTaxName);

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $itemAmount);
        $I->selectOption('select[name=tax_select1]', $itemTaxName . ': ' . number_format($itemTaxRate, 3) . '%');
        $I->click('Save');
        $I->wait(1);

        $I->amOnPage('/invoices/create');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        // check tax and discount rounding
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('#discount', $discount);
        $I->fillField('table.invoice-table tbody tr:nth-child(1) td:nth-child(2) input.tt-input', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) td:nth-child(6) input', $itemDiscount);
        $I->fillField('table.invoice-table tbody tr:nth-child(1) td:nth-child(5) input', $quantity);

        $I->click('Mark Sent');
        $I->wait(3);

        $total = $itemAmount * $quantity;
        $total -= round($total * round($itemDiscount, 2) / 100, 2);
        $total -= round($total * round($discount, 2) / 100, 2);
        $total += round($total * $itemTaxRate / 100, 2);

        $I->see(number_format($total, 2));
    }
}

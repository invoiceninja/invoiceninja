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

        $clientEmail = $this->faker->safeEmail;
        $itemTaxName = $this->faker->word;
        $itemTaxRate = rand(1, 1000) / 10;
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

        $I->amOnPage('/invoices/create');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        // check tax and discount rounding
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('#discount', $discount);
        $this->fillItem($I, 1, 'Item', 'Notes', $itemAmount, $quantity, $itemDiscount);

        $I->click('Mark Sent');
    }

    private function fillItem(AcceptanceTester $I, $row, $product, $description, $cost, $quantity, $discount)
    {
        $row_selector = sprintf('table.product-table tr:nth-child(%d) ', $row);

        $I->fillField($row_selector.'#product_key', $product);
        $I->fillField($row_selector.'textarea', $description);
        $I->fillField($row_selector.'td:nth-child(4) input', $cost);
        $I->fillField($row_selector.'td:nth-child(5) input', $quantity);
        $I->fillField($row_selector.'td:nth-child(6) input', $discount);
    }
}

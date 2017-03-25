<?php

use Codeception\Util\Fixtures;
use Faker\Factory;

class CheckBalanceCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function checkBalance(AcceptanceTester $I)
    {
        $I->wantTo('ensure the balance is correct');

        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);
        $productPrice = $this->faker->numberBetween(1, 20);

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->wait(1);
        $I->see($clientEmail);

        $clientId = $I->grabFromCurrentUrl('~clients/(\d+)~');

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $productPrice);
        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);

        // create invoice
        $I->amOnPage('/invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->click('Mark Sent');
        $I->wait(5);
        $I->see($clientEmail);
        $invoiceId = $I->grabFromCurrentUrl('~invoices/(\d+)~');
        $I->amOnPage("/clients/{$clientId}");
        $I->see('Balance $' . $productPrice);

        // update the invoice
        $I->amOnPage('/invoices/' . $invoiceId);
        $I->fillField(['name' => 'invoice_items[0][qty]'], 2);
        $I->click('Save Invoice');
        $I->wait(1);
        $I->amOnPage("/clients/{$clientId}");
        $I->see('Balance $' . ($productPrice * 2));

        // enter payment
        $I->amOnPage("/payments/create/{$clientId}/{$invoiceId}");
        $I->click('Save');
        $I->wait(1);
        $I->see('Balance $0.00');
        $I->see('Paid to Date $' . ($productPrice * 2));

        // archive the invoice
        $I->amOnPage('/invoices/' . $invoiceId);
        $I->executeJS('submitBulkAction("archive")');
        $I->wait(1);
        $I->amOnPage("/clients/{$clientId}");
        $I->see('Balance $0.00');
        $I->see('Paid to Date $' . ($productPrice * 2));

        // delete the invoice
        $I->amOnPage('/invoices/' . $invoiceId);
        $I->executeJS('submitBulkAction("restore")');
        $I->wait(2);
        $I->executeJS('submitBulkAction("delete")');
        $I->wait(1);
        $I->amOnPage("/clients/{$clientId}");
        $I->see('Balance $0.00');
        $I->see('Paid to Date $0.00');

        // restore the invoice
        $I->amOnPage('/invoices/' . $invoiceId);
        $I->executeJS('submitBulkAction("restore")');
        $I->wait(1);
        $I->amOnPage("/clients/{$clientId}");
        $I->see('Balance $0.00');
        $I->see('Paid to Date $' . ($productPrice * 2));
    }
}

<?php

use Codeception\Util\Fixtures;
use \AcceptanceTester;
use Faker\Factory;

class OnlinePaymentCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function onlinePayment(AcceptanceTester $I)
    {
        $I->wantTo('test an online payment');

        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);

        // set gateway info
        $I->wantTo('create a gateway');
        $I->amOnPage('/company/payments');

        if (strpos($I->grabFromCurrentUrl(), 'create') !== false) {
            $I->fillField(['name' =>'23_apiKey'], Fixtures::get('gateway_key'));
            $I->selectOption('#token_billing_type_id', 4);
            $I->click('Save');
            $I->see('Successfully created gateway');
        }

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'email'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $this->faker->numberBetween(1, 20));
        $I->click('Save');
        $I->see($productKey);

        // create invoice
        $I->amOnPage('/invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('Save');
        $I->see($clientEmail);

        // enter payment
        $clientId = $I->grabFromDatabase('contacts', 'client_id', ['email' => $clientEmail]);
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId]);
        $invitationKey = $I->grabFromDatabase('invitations', 'invitation_key', ['invoice_id' => $invoiceId]);

        $clientSession = $I->haveFriend('client');
        $clientSession->does(function(AcceptanceTester $I) use ($invitationKey) {
            $I->amOnPage('/view/' . $invitationKey);
            $I->click('Pay Now');

            $I->fillField(['name' => 'first_name'], $this->faker->firstName);
            $I->fillField(['name' => 'last_name'], $this->faker->lastName);
            $I->fillField(['name' => 'address1'], $this->faker->streetAddress);
            $I->fillField(['name' => 'address2'], $this->faker->streetAddress);
            $I->fillField(['name' => 'city'], $this->faker->city);
            $I->fillField(['name' => 'state'], $this->faker->state);
            $I->fillField(['name' => 'postal_code'], $this->faker->postcode);
            $I->selectDropdown($I, 'United States', '.country-select .dropdown-toggle');
            $I->fillField(['name' => 'card_number'], '4242424242424242');
            $I->fillField(['name' => 'cvv'], '1234');
            $I->selectOption('#expiration_month', 12);
            $I->selectOption('#expiration_year', date('Y'));
            $I->click('.btn-success');
            $I->see('Successfully applied payment');
        });

        // create recurring invoice and auto-bill
        $I->amOnPage('/recurring_invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->checkOption('#auto_bill');
        $I->executeJS('preparePdfData(\'email\')');
        $I->wait(2);
        $I->see("$0.00");
 
   }
}
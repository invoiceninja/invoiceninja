<?php

use Faker\Factory;
use Codeception\Util\Fixtures;

class ExpenseCest
{
    /**
     * @var \Faker\Generator
     */
    private $faker;
    
    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function createExpense(AcceptanceTester $I)
    {
        $I->wantTo('Create an expense');

        $vendorName = $this->faker->name;
        $clientEmail = $this->faker->safeEmail;
        $amount = $this->faker->numberBetween(10, 20);

        // create vendor
        $I->amOnPage('/vendors/create');
        $I->fillField(['name' => 'name'], $vendorName);
        $I->click('Save');
        $I->see($vendorName);
        $vendorId = $I->grabFromDatabase('vendors', 'id', ['name' => $vendorName]);

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        // create expense
        $I->amOnPage('/expenses/create');
        $I->fillField(['name' => 'amount'], $amount);
        $I->selectDropdown($I, $vendorName, '.vendor-select .dropdown-toggle');
        $I->selectDropdown($I, $clientEmail, '.client-select .dropdown-toggle');
        $I->click('Save');
        $I->wait(2);
        $I->seeInDatabase('expenses', ['vendor_id' => $vendorId]);

        // invoice expense
        $I->executeJS('submitAction(\'invoice\')');
        $I->wait(2);
        $I->click('Save');
        $I->wait(2);
        $I->see($clientEmail);
        $I->see($amount);
    }
}

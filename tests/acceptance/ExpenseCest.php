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
        $categoryName = $this->faker->text(20);
        $clientName = $this->faker->name;
        $clientEmail = $this->faker->safeEmail;
        $amount = $this->faker->numberBetween(10, 20);

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'name'], $clientName);
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);
        $clientId = $I->grabFromDatabase('clients', 'id', ['name' => $clientName]);

        // create expense
        $I->amOnPage('/expenses/create');
        $I->fillField(['name' => 'amount'], $amount);
        $I->selectDropdownCreate($I, 'vendor', $vendorName);
        $I->selectDropdownCreate($I, 'expense_category', $categoryName, 'category');
        $I->selectDropdown($I, $clientName, '.client-select .dropdown-toggle');
        $I->click('Save');
        $I->wait(2);

        $vendorId = $I->grabFromDatabase('vendors', 'id', ['name' => $vendorName]);
        $categoryId = $I->grabFromDatabase('expense_categories', 'id', ['name' => $categoryName]);
        $I->seeInDatabase('expenses', [
            'client_id' => $clientId,
            'vendor_id' => $vendorId,
            'expense_category_id' => $categoryId
        ]);

        // invoice expense
        $I->executeJS('submitAction(\'invoice\')');
        $I->wait(2);
        $I->click('Save Draft');
        $I->wait(2);
        $I->see($clientEmail);
        $I->see($amount);
    }
}

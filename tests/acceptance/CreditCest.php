<?php

use \AcceptanceTester;
use App\Models\Credit;
use Faker\Factory;
use Codeception\Util\Fixtures;

class CreditCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function create(AcceptanceTester $I)
    {
        $note = $this->faker->catchPhrase;
        $clientName = $I->grabFromDatabase('clients', 'name');

        $I->wantTo('Create a credit');
        $I->amOnPage('/credits/create');

        $I->selectDropdown($I, $clientName, '.client-select .dropdown-toggle');
        $I->fillField(['name' => 'amount'], rand(50, 200));
        $I->fillField(['name' => 'private_notes'], $note);
        $I->selectDataPicker($I, '#credit_date', 'now + 1 day');
        $I->click('Save');

        $I->see('Successfully created credit');
        $I->seeInDatabase('credits', array('private_notes' => $note));
    
        $I->amOnPage('/credits');
        $I->seeCurrentUrlEquals('/credits');
        $I->see($clientName);
    }
    
}
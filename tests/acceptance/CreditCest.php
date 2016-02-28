<?php

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
        $clientEmail = $this->faker->safeEmail;

        $I->wantTo('Create a credit');

        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $I->amOnPage('/credits/create');
        $I->selectDropdown($I, $clientEmail, '.client-select .dropdown-toggle');
        $I->fillField(['name' => 'amount'], rand(50, 200));
        $I->fillField(['name' => 'private_notes'], $note);
        $I->selectDataPicker($I, '#credit_date', 'now + 1 day');
        $I->click('Save');

        $I->see('Successfully created credit');
        $I->seeInDatabase('credits', array('private_notes' => $note));
    
        $I->amOnPage('/credits');
        $I->seeCurrentUrlEquals('/credits');
        $I->see($clientEmail);
    }
    
}
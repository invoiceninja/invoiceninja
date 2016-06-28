<?php

use Codeception\Util\Fixtures;
use Faker\Factory;

class GoProCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $this->faker = Factory::create();
    }

    public function signUpAndGoPro(AcceptanceTester $I)
    {
        $userEmail = $this->faker->safeEmail;
        $userPassword = $this->faker->password;

        $I->wantTo('test purchasing a pro plan');
        $I->amOnPage('/invoice_now');

        $I->click('Sign Up');
        $I->wait(1);

        $I->checkOption('#terms_checkbox');
        $I->fillField(['name' =>'new_first_name'], $this->faker->firstName);
        $I->fillField(['name' =>'new_last_name'], $this->faker->lastName);
        $I->fillField(['name' =>'new_email'], $userEmail);
        $I->fillField(['name' =>'new_password'], $userPassword);
        $I->click('Save');
        $I->wait(1);

        $I->amOnPage('/dashboard');
        $I->click('Upgrade');
        $I->wait(1);

        $I->click('#changePlanButton');
        $I->wait(1);

        $I->click('Pay Now');
        $I->wait(1);

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
        $I->wait(1);

        $I->see('Successfully applied payment');

        $I->amOnPage('/dashboard');
        $I->dontSee('Go Pro');
   }
}

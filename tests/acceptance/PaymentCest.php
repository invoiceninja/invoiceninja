<?php

use \AcceptanceTester;
use App\Models\Payment;
use Faker\Factory;

class PaymentCest
{
    private $faker;

    public function _before(AcceptanceTester $I)
    {
        $I->checkIfLogin($I);

        $this->faker = Factory::create();
    }

    public function create(AcceptanceTester $I)
    {
        $clientName = $I->grabFromDatabase('clients', 'name');
        $amount = rand(1, 30);

        $I->wantTo('enter a payment');
        $I->amOnPage('/payments/create');

        $I->selectDropdown($I,  $clientName, '.client-select .dropdown-toggle');
        $I->selectDropdownRow($I, 1, '.invoice-select .combobox-container');
        $I->fillField(['name' => 'amount'], $amount);
        $I->selectDropdownRow($I, 1, 'div.panel-body div.form-group:nth-child(4) .combobox-container');
        $I->selectDataPicker($I, '#payment_date', 'now + 1 day');
        $I->fillField(['name' => 'transaction_reference'], $this->faker->text(12));

        $I->click('Save');

        $I->see('Successfully created payment');
        $I->seeInDatabase('payments', ['amount' => number_format($amount, 2)]);
    }

    public function editPayment(AcceptanceTester $I)
    {
        $ref = $this->faker->text(12);
        
        $I->wantTo('edit a payment');
        $I->amOnPage('/payments/1/edit');

        $I->selectDataPicker($I, '#payment_date', 'now + 2 day');
        $I->fillField(['name' => 'transaction_reference'], $ref);

        $I->click('Save');
        $I->seeInDatabase('payments', ['transaction_reference' => $ref]);
    }

    public function listPayments(AcceptanceTester $I)
    {
        $I->wantTo('list payments');
        $I->amOnPage('/payments');

        $I->seeNumberOfElements('tbody tr[role=row]', [1, 10]);
    }

    /*
    public function delete(AcceptanceTester $I)
    {
        $I->wantTo('delete a payment');

        $I->amOnPage('/payments');
        $I->seeCurrentUrlEquals('/payments');
        $I->wait(3);

        if ($num_payments = Payment::all()->count()) {
            $row_rand = sprintf('tbody tr:nth-child(%d)', rand(1, $num_payments));
            //show button
            $I->executeJS(sprintf('$("%s div").css("visibility", "visible")', $row_rand));

            //dropdown
            $I->click($row_rand . ' button');

            //click to delete button
            $I->click($row_rand . ' ul li:nth-last-child(1) a');
            $I->acceptPopup();
        }
    }
    */
}

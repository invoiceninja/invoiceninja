<?php

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
        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->text(10);
        $amount = rand(1, 10);

        $I->wantTo('enter a payment');

        // create client
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        // create product
        $I->amOnPage('/products/create');
        $I->fillField(['name' => 'product_key'], $productKey);
        $I->fillField(['name' => 'notes'], $this->faker->text(80));
        $I->fillField(['name' => 'cost'], $this->faker->numberBetween(11, 20));
        $I->click('Save');
        $I->wait(1);
        //$I->see($productKey);

        // create invoice
        $I->amOnPage('/invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->fillField('table.invoice-table tbody tr:nth-child(1) #product_key', $productKey);
        $I->click('table.invoice-table tbody tr:nth-child(1) .tt-selectable');
        $I->click('Mark Sent');
        $I->wait(2);
        $I->see($clientEmail);

        $I->amOnPage('/payments/create');
        $I->selectDropdown($I,  $clientEmail, '.client-select .dropdown-toggle');
        $I->selectDropdownRow($I, 1, '.invoice-select .combobox-container');
        $I->fillField(['name' => 'amount'], $amount);
        $I->selectDropdown($I, 'Cash', '.payment-type-select .dropdown-toggle');
        $I->selectDataPicker($I, '#payment_date', 'now + 1 day');
        $I->fillField(['name' => 'transaction_reference'], $this->faker->text(12));

        $I->click('Save');
        $I->wait(1);

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

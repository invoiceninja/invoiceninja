<?php

use \AcceptanceTester;
use Faker\Factory;

class InvoiceCest
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

    public function createInvoice(AcceptanceTester $I)
    {
        $clientEmail = $this->faker->safeEmail;

        $I->wantTo('create an invoice');

        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'email'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $I->amOnPage('/invoices/create');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->selectDataPicker($I, '#invoice_date');
        $I->selectDataPicker($I, '#due_date', '+ 15 day');
        $I->fillField('#po_number', rand(100, 200));
        $I->fillField('#discount', rand(0, 20));

        $this->fillItems($I);

        $I->click('#saveButton');
        $I->wait(1);
        $I->see($invoiceNumber);
    }

    public function createRecurringInvoice(AcceptanceTester $I)
    {
        $clientEmail = $this->faker->safeEmail;

        $I->wantTo('create a recurring invoice');
        
        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'email'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $I->amOnPage('/recurring_invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->selectDataPicker($I, '#end_date', '+ 1 week');
        $I->fillField('#po_number', rand(100, 200));
        $I->fillField('#discount', rand(0, 20));
        
        $this->fillItems($I);
        
        $I->executeJS("submitAction('email')");
        $I->wait(1);
        $I->see($clientEmail);

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');
        $I->click('Recurring Invoice');
        $I->see($clientEmail);

        $I->click('#lastInvoiceSent');
        $I->see($invoiceNumber);
    }
    
    public function editInvoice(AcceptanceTester $I)
    {
        $I->wantTo('edit an invoice');
        
        $I->amOnPage('/invoices/1/edit');

        //change po_number with random number
        $po_number = rand(100, 300);
        $I->fillField('#po_number', $po_number);

        //save
        $I->executeJS('submitAction()');
        $I->wait(1);

        //check if po_number was updated
        $I->seeInDatabase('invoices', ['po_number' => $po_number]);
    }

    public function cloneInvoice(AcceptanceTester $I)
    {
        $I->wantTo('clone an invoice');
        $I->amOnPage('/invoices/1/clone');
        
        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        $I->executeJS('submitAction()');
        $I->wait(1);

        $I->see($invoiceNumber);
    }

    
    /*
    public function deleteInvoice(AcceptanceTester $I)
    {
        $I->wantTo('delete an invoice');

        $I->amOnPage('/invoices');
        $I->seeCurrentUrlEquals('/invoices');

        //delete invoice
        $I->executeJS(sprintf('deleteEntity(%d)', $id = Helper::getRandom('Invoice', 'public_id', ['is_quote' => 0])));
        $I->acceptPopup();
        $I->wait(5);

        //check if invoice was removed
        $I->seeInDatabase('invoices', ['public_id' => $id, 'is_deleted' => true]);
    }
    */


    private function fillItems(AcceptanceTester $I, $max = 2)
    {
        for ($i = 1; $i <= $max; $i++) {
            $row_selector = sprintf('table.invoice-table tbody tr:nth-child(%d) ', $i);

            $product_key  = $this->faker->text(10);
            $description  = $this->faker->text(80);
            $unit_cost    = $this->faker->randomFloat(2, 0, 100);
            $quantity     = $this->faker->randomDigitNotNull;

            $I->fillField($row_selector.'#product_key', $product_key);
            $I->fillField($row_selector.'textarea', $description);
            $I->fillField($row_selector.'td:nth-child(4) input', $unit_cost);
            $I->fillField($row_selector.'td:nth-child(5) input', $quantity);
        }
    }
}

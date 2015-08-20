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
        $clientName = $I->grabFromDatabase('clients', 'name');

        $I->wantTo('create an invoice');
        $I->amOnPage('/invoices/create');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        $I->selectDropdown($I, $clientName, '.client_select .dropdown-toggle');
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
        $clientName = $I->grabFromDatabase('clients', 'name');

        $I->wantTo('create a recurring invoice');
        $I->amOnPage('/recurring_invoices/create');

        $I->selectDropdown($I, $clientName, '.client_select .dropdown-toggle');
        //$I->selectOption('#frequency_id', Helper::getRandom('Frequency'));
        $I->selectDataPicker($I, '#start_date');
        $I->selectDataPicker($I, '#end_date', '+ 1 week');

        $I->fillField('#po_number', rand(100, 200));
        $I->fillField('#discount', rand(0, 20));

        $this->fillItems($I);

        $I->executeJS('submitAction()');
        $I->wait(1);
        $I->see($clientName);
    }    

    public function editInvoice(AcceptanceTester $I)
    {
        $I->wantTo('edit an invoice');
        
        $I->amOnPage('/invoices/1/edit');

        //change po_number with random number
        $po_number = rand(100, 300);
        $I->fillField('po_number', $po_number);

        //save
        $I->executeJS('submitAction()');
        $I->wait(1);

        //check if po_number was updated
        $I->seeInDatabase('invoices', ['po_number' => $po_number]);
    }

    public function cloneInvoice(AcceptanceTester $I)
    {
        $I->wantTo('clone an invoice');
        $I->amOnPage('invoices/1/clone');
        
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

    /*
    public function indexInvoice(AcceptanceTester $I)
    {
        $I->wantTo('list invoices');

        $I->amOnPage('/invoices');
        $I->seeCurrentUrlEquals('/invoices');

        $random_invoice_number = Helper::getRandom('Invoice', 'invoice_number', [
            'is_quote' => 0,
            'is_recurring' => false
        ]);

        if ($random_invoice_number) {
            $I->wait(2);
            $I->see($random_invoice_number);
        }
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

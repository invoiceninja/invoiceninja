<?php

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
        $itemTaxName = 'TAX_21';

        $I->wantTo('create an invoice');

        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $clientId = $I->grabFromCurrentUrl('~clients/(\d+)~');

        $I->amOnPage('/tax_rates/create');
        $I->fillField(['name' => 'name'], $itemTaxName);
        $I->fillField(['name' => 'rate'], 21);
        $I->click('Save');
        $I->see($itemTaxName);

        $I->amOnPage('/invoices/create');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        // check tax and discount rounding
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->selectDataPicker($I, '#invoice_date');
        $I->selectDataPicker($I, '#due_date', '+ 15 day');
        $I->fillField('#po_number', rand(100, 200));
        $I->fillField('#discount', 15);
        $I->selectOption('#taxRateSelect1', $itemTaxName . ' 21%');
        $this->fillItem($I, 1, 'Item', 'Notes', 64.50, 3);

        $I->click('#saveButton');
        $I->wait(1);
        $I->see($invoiceNumber);
        $I->see('199.01');

        $I->amOnPage("/clients/{$clientId}#invoices");
        $I->see('199.01');
    }

    /*
    public function editInvoice(AcceptanceTester $I)
    {
        $I->wantTo('edit an invoice');


        // Check all language files
        $count = $I->grabNumRecords('date_formats');
        for ($i=1; $i<=$count; $i++) {
            $format = $I->grabFromDatabase('date_formats', 'format', ['id' => $i]);
            $date = mktime(0, 0, 0, 12, 31, date('Y'));
            $value = date($format, $date);

            $I->amOnPage('/settings/localization');
            $I->selectOption('date_format_id', $value);
            $I->click('Save');

            //change po_number with random number
            $I->amOnPage('/invoices/1/edit');
            $I->selectDataPicker($I, '#invoice_date');
            $po_number = rand(1, 10000);
            $I->fillField('#po_number', $po_number);

            //save
            $I->executeJS('submitAction()');
            $I->wait(1);

            //check if po_number was updated
            $I->seeInDatabase('invoices', [
                'po_number' => $po_number,
                'invoice_date' => date('Y-m-d')
            ]);
        }
    }
    */

    public function createRecurringInvoice(AcceptanceTester $I)
    {
        $clientEmail = $this->faker->safeEmail;

        $I->wantTo('create a recurring invoice');

        $I->amOnPage('/clients/create');
        $I->fillField(['name' => 'contacts[0][email]'], $clientEmail);
        $I->click('Save');
        $I->see($clientEmail);

        $I->amOnPage('/recurring_invoices/create');
        $I->selectDropdown($I, $clientEmail, '.client_select .dropdown-toggle');
        $I->selectDataPicker($I, '#end_date', '+ 1 week');
        $I->fillField('#po_number', rand(100, 200));
        $I->fillField('#discount', rand(0, 20));

        $this->fillItems($I);

        $I->executeJS("submitAction('email')");
        $I->wait(3);
        $I->see($clientEmail);

        $I->click('#lastSent');
        $I->see($clientEmail);

        $I->click('Recurring Invoice');
        $I->see($clientEmail);
    }


    public function cloneInvoice(AcceptanceTester $I)
    {
        $I->wantTo('clone an invoice');
        $I->amOnPage('/invoices/1/clone');

        $invoiceNumber = $I->grabAttributeFrom('#invoice_number', 'value');

        $I->executeJS('submitAction()');
        $I->wait(3);

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
        for ($row = 1; $row <= $max; $row++) {

            $product = $this->faker->text(10);
            $description = $this->faker->text(80);
            $cost = $this->faker->randomFloat(2, 0, 100);
            $quantity = $this->faker->randomDigitNotNull;

            $this->fillItem($I, $row, $product, $description, $cost, $quantity);
        }
    }

    private function fillItem(AcceptanceTester $I, $row, $product, $description, $cost, $quantity)
    {
        $row_selector = sprintf('table.invoice-table tbody tr:nth-child(%d) ', $row);

        $I->fillField($row_selector.'#product_key', $product);
        $I->fillField($row_selector.'textarea', $description);
        $I->fillField($row_selector.'td:nth-child(4) input', $cost);
        $I->fillField($row_selector.'td:nth-child(5) input', $quantity);
    }
}

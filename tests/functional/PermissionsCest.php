<?php

use Faker\Factory;
use Codeception\Util\Fixtures;

class PermissionsCest
{
    /**
     * @var \Faker\Generator
     */
    private $faker;

    private $entityArray;

    public function _before(FunctionalTester $I)
    {
        $this->faker = Factory::create();
        $I->checkIfLogin($I);

        $this->entityArray = [
            'proposal',
            'expense',
            'project',
            'vendor',
            'product',
            'task',
            'quote',
            'credit',
            'payment',
            'contact',
            'invoice',
            'client',
            'recurring_invoice',
            'reports',
        ];

    }

    public function setViewPermissions(FunctionalTester $I)
    {
        $I->wantTo('create a view only permission user');

        $permissions = [];

        foreach($this->entityArray as $item)
            array_push($permissions, 'view_' . $item);

        $I->updateInDatabase('users',
            ['is_admin' => 0,
            'permissions' => json_encode(array_diff(array_values($permissions),[0]))
        ],
            ['email' => Fixtures::get('permissions_username')]
        );
    }

    /*
     * Test View Permissions
     *
     *  See 200 response for an individual ENTITY record
     *
     */




    public function viewInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewClient(FunctionalTester $I)
    {
        $I->amOnPage('/clients/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewProduct(FunctionalTester $I)
    {
        $I->amOnPage('/products/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewPayment(FunctionalTester $I)
    {
        $I->amOnPage('/payments/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewQuote(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewRecurringInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/recurring_invoices/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewCredit(FunctionalTester $I)
    {
        $I->amOnPage('/credits/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewProposal(FunctionalTester $I)
    {
        $I->amOnPage('/proposals/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewProject(FunctionalTester $I)
    {
        $I->amOnPage('/projects/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewTask(FunctionalTester $I)
    {
        $I->amOnPage('/tasks/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewExpense(FunctionalTester $I)
    {
        $I->amOnPage('/expenses/1');
        $I->seeResponseCodeIs(200);
    }

    public function viewVendor(FunctionalTester $I)
    {
        $I->amOnPage('/vendors/1');
        $I->seeResponseCodeIs(200);
    }

    /*
     * Test view permissions for lists
     */


    public function viewInvoices(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/');
        $I->seeResponseCodeIs(200);
    }

    public function viewClients(FunctionalTester $I)
    {
        $I->amOnPage('/clients/');
        $I->seeResponseCodeIs(200);
    }

    public function viewProducts(FunctionalTester $I)
    {
        $I->amOnPage('/products/');
        $I->seeResponseCodeIs(200);
    }

    public function viewPayments(FunctionalTester $I)
    {
        $I->amOnPage('/payments/');
        $I->seeResponseCodeIs(200);
    }

    public function viewQuotes(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/');
        $I->seeResponseCodeIs(200);
    }

    public function viewRecurringInvoices(FunctionalTester $I)
    {
        $I->amOnPage('/recurring_invoices/');
        $I->seeResponseCodeIs(200);
    }

    public function viewCredits(FunctionalTester $I)
    {
        $I->amOnPage('/credits/');
        $I->seeResponseCodeIs(200);
    }

    public function viewProposals(FunctionalTester $I)
    {
        $I->amOnPage('/proposals/');
        $I->seeResponseCodeIs(200);
    }

    public function viewProjects(FunctionalTester $I)
    {
        $I->amOnPage('/projects/');
        $I->seeResponseCodeIs(200);
    }

    public function viewTasks(FunctionalTester $I)
    {
        $I->amOnPage('/tasks/');
        $I->seeResponseCodeIs(200);
    }

    public function viewExpenses(FunctionalTester $I)
    {
        $I->amOnPage('/expenses/');
        $I->seeResponseCodeIs(200);
    }

    public function viewVendors(FunctionalTester $I)
    {
        $I->amOnPage('/vendors/');
        $I->seeResponseCodeIs(200);
    }

    /*
     * Test Create permissions when only VIEW enabled
     */

    public function createInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/create');
        $I->seeResponseCodeIs(403);
    }

    public function createClient(FunctionalTester $I)
    {
        $I->amOnPage('/clients/create');
        $I->seeResponseCodeIs(403);
    }

    public function createProduct(FunctionalTester $I)
    {
        $I->amOnPage('/products/create');
        $I->seeResponseCodeIs(403);
    }

    public function createPayment(FunctionalTester $I)
    {
        $I->amOnPage('/payments/create');
        $I->seeResponseCodeIs(403);
    }

    public function createQuote(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/create');
        $I->seeResponseCodeIs(403);
    }

    public function createRecurringInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/recurring_invoices/create');
        $I->seeResponseCodeIs(403);
    }

    public function createCredit(FunctionalTester $I)
    {
        $I->amOnPage('/credits/create');
        $I->seeResponseCodeIs(403);
    }

    public function createProposal(FunctionalTester $I)
    {
        $I->amOnPage('/proposals/create');
        $I->seeResponseCodeIs(403);
    }

    public function createProject(FunctionalTester $I)
    {
        $I->amOnPage('/projects/create');
        $I->seeResponseCodeIs(403);
    }

    public function createTask(FunctionalTester $I)
    {
        $I->amOnPage('/tasks/create');
        $I->seeResponseCodeIs(403);
    }

    public function createExpense(FunctionalTester $I)
    {
        $I->amOnPage('/expenses/create');
        $I->seeResponseCodeIs(403);
    }

    public function createVendor(FunctionalTester $I)
    {
        $I->amOnPage('/vendors/create');
        $I->seeResponseCodeIs(403);
    }


    /****
     *  Test the edge case with Invoice and Quote Permissions
     */

    public function setQuoteOnlyPermissions(FunctionalTester $I)
    {
        $I->wantTo('create a quote view only permission user');

        $permissions = [];

        array_push($permissions, 'view_quote');
        array_push($permissions, 'edit_quote');
        array_push($permissions, 'create_quote');

        $I->updateInDatabase('users',
            ['is_admin' => 0,
                'permissions' => json_encode(array_diff(array_values($permissions),[0]))
            ],
            ['email' => Fixtures::get('permissions_username')]
        );
    }

    public function testCreateInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/create');
        $I->seeResponseCodeIs(403);
    }


    /*
     * 

    public function testViewInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/1');
        $I->seeResponseCodeIs(403);
    }

    public function testEditInvoice(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/11/edit');
        $I->seeResponseCodeIs(403);
    }

    */

    public function testCreateQuote(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/create');
        $I->seeResponseCodeIs(200);
    }

    public function testEditQuote(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/1/edit');
        $I->seeResponseCodeIs(200);
    }

    public function testViewQuote(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/1');
        $I->seeResponseCodeIs(200);
    }

    public function setInvoiceOnlyPermissions(FunctionalTester $I)
    {
        $I->wantTo('create a invoice view only permission user');

        $permissions = [];

        array_push($permissions, 'view_invoice');
        array_push($permissions, 'edit_invoice');
        array_push($permissions, 'create_invoice');

        $I->updateInDatabase('users',
            ['is_admin' => 0,
                'permissions' => json_encode(array_diff(array_values($permissions),[0]))
            ],
            ['email' => Fixtures::get('permissions_username')]
        );
    }


    public function testCreateInvoiceOnly(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/create');
        $I->seeResponseCodeIs(200);
    }

    public function testViewInvoiceOnly(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/1');
        $I->seeResponseCodeIs(200);
    }

    public function testEditInvoiceOnly(FunctionalTester $I)
    {
        $I->amOnPage('/invoices/1/edit');
        $I->seeResponseCodeIs(200);
    }

    public function testCreateQuoteOnly(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/create');
        $I->seeResponseCodeIs(403);
    }

    public function testEditQuoteOnly(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/1/edit');
        $I->seeResponseCodeIs(200);
    }

    public function testViewQuoteOnly(FunctionalTester $I)
    {
        $I->amOnPage('/quotes/1');
        $I->seeResponseCodeIs(403);
    }
}

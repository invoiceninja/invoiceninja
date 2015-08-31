<?php 

$I = new AcceptanceTester($scenario);

$I->wantTo('Test all pages load');
$I->amOnPage('/login');
//$I->see(trans('texts.forgot_password'));

// Login as test user
$I->fillField(['name' => 'email'], 'hillelcoren@gmail.com');
$I->fillField(['name' => 'password'], '4uejs%2ksl#271df');
$I->click('Let\'s go');
$I->see('Dashboard');

// Top level navigation
$I->amOnPage('/dashboard');
$I->see('Total Revenue');

$I->amOnPage('/clients');
$I->see('Clients', 'li');

$I->amOnPage('/clients/create');
$I->see('Clients', 'li');
$I->see('Create');

$I->amOnPage('/credits');
$I->see('Credits', 'li');

$I->amOnPage('/credits/create');
$I->see('Credits', 'li');
$I->see('Create');

$I->amOnPage('/tasks');
$I->see('Tasks', 'li');

$I->amOnPage('/tasks/create');
$I->see('Tasks', 'li');
$I->see('Create');

$I->amOnPage('/invoices');
$I->see('Invoices', 'li');

$I->amOnPage('/invoices/create');
$I->see('Invoices', 'li');
$I->see('Create');

$I->amOnPage('/quotes');
$I->see('Quotes', 'li');

$I->amOnPage('/quotes/create');
$I->see('Quotes', 'li');
$I->see('Create');

$I->amOnPage('/payments');
$I->see('Payments', 'li');

$I->amOnPage('/payments/create');
$I->see('Payments', 'li');
$I->see('Create');

// Settings pages
$I->amOnPage('/company/details');
$I->see('Details');

$I->amOnPage('/gateways/create');
$I->see('Add Gateway');

$I->amOnPage('/company/products');
$I->see('Product Settings');

$I->amOnPage('/company/import_export');
$I->see('Import');

$I->amOnPage('/company/advanced_settings/invoice_settings');
$I->see('Invoice Fields');

$I->amOnPage('/company/advanced_settings/invoice_design');
$I->see('Invoice Design');

$I->amOnPage('/company/advanced_settings/email_templates');
$I->see('Invoice Email');

$I->amOnPage('/company/advanced_settings/charts_and_reports');
$I->see('Data Visualizations');

$I->amOnPage('/company/advanced_settings/user_management');
$I->see('Add User');

//try to logout
$I->click('#myAccountButton');
$I->see('Log Out');
$I->click('Log Out');

// Miscellaneous pages
$I->amOnPage('/terms');
$I->see('Terms');

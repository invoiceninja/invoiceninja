<?php

use Faker\Factory;

class GatewayFeesCest
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

    public function checkLineItemFee(AcceptanceTester $I)
    {
        $clientEmail = $this->faker->safeEmail;
        $productKey = $this->faker->word();
        $taxName = $this->faker->word();

        //$cost = $this->faker->randomFloat(2, 50, 100);
        //$feeAmount = $this->faker->randomFloat(2, 1, 5);
        //$taxRate = $this->faker->randomFloat(3, 5, 15);
        //$feePercent = $this->faker->randomFloat(3, 1, 5);

        $cost = 100;
        $feeAmount = 10;
        $taxRate = 10;
        $feePercent = 10;

        $total = $cost + ($cost * $taxRate / 100);
        $fee = $feeAmount + ($total * $feeAmount / 100);
        $feeWithTax = $fee + ($fee * $taxRate / 100);
        $partialFee = $feeAmount + ($total / 2 * $feeAmount / 100);
        $partialFeeWithTax = $partialFee + ($partialFee * $taxRate / 100);

        $I->createClient($I, $clientEmail);
        $I->createGateway($I);
        $this->configureFees($I, $feeAmount, $feePercent);

        // line item gateway fee
        $this->configureLineItemTaxRates($I, $taxName, $taxRate);
        $productKey = $this->faker->word();
        $I->createProduct($I, $productKey, $cost, $taxName, $taxRate);

        // without taxing the fee
        $this->configureGatewayFeeTax($I);
        $this->createInvoice($I, $clientEmail, $productKey, $total, $fee);

        // with taxing the fee
        $this->configureGatewayFeeTax($I, $taxName, $taxRate);
        $this->createInvoice($I, $clientEmail, $productKey, $total, $feeWithTax);

        // partial invoice (resaving invoice between payments)
        $invitationKey = $this->createInvoice($I, $clientEmail, $productKey, $total, $partialFeeWithTax, $total / 2);
        $invoiceId = $I->grabFromDatabase('invitations', 'invoice_id', ['invitation_key' => $invitationKey]);
        $invoicePublicId = $I->grabFromDatabase('invoices', 'public_id', ['id' => $invoiceId]);
        $I->amOnPage('/invoices/' . $invoicePublicId . '/edit');
        $I->wait(3);
        $I->click('Save Invoice');
        $I->wait(3);
        $this->createPayment($I, $invitationKey, $total + $partialFeeWithTax, 0, $partialFeeWithTax);
    }

    private function configureGatewayFeeTax($I, $taxName = '', $taxRate = '')
    {
        if ($taxName && $taxRate) {
            $taxOption = $taxName . ': ' . number_format($taxRate, 3) . '%';
        } else {
            $taxOption = '';
        }

        $I->amOnPage('/settings/online_payments#fees');
        $I->executeJS('javascript:showLimitsModal(\'Credit Card\', 1)');
        $I->click('Fees');
        $I->selectOption('tax_rate1', $taxOption);
        $I->click('#modalSave');

        $I->executeJS('javascript:showLimitsModal(\'Bank Transfer\', 2)');
        $I->click('Fees');
        $I->selectOption('tax_rate1', $taxOption);
        $I->click('#modalSave');
    }

    private function configureLineItemTaxRates($I, $taxName, $taxRate)
    {
        $taxOption = $taxName . ': ' . number_format($taxRate, 3) . '%';
        $I->createTaxRate($I, $taxName, $taxRate);

        $I->amOnPage('/settings/tax_rates');
        $I->checkOption('#invoice_item_taxes');
        $I->click('Save');

        // disable the default invoice tax
        //$I->amOnPage('/settings/tax_rates');
        //$I->selectOption('#default_tax_rate_id', '');
        //$I->click('Save');
    }

    private function configureFees($I, $feeAmount, $feePercent)
    {
        $I->createGateway($I);

        // enable gateway fees
        $I->amOnPage('/settings/online_payments#fees');
        $I->checkOption('gateway_fee_enabled');
        $I->click('#formSave');

        $I->executeJS('javascript:showLimitsModal(\'Credit Card\', 1)');
        $I->click('Fees');
        $I->fillField(['name' => 'fee_amount'], $feeAmount);
        $I->fillField(['name' => 'fee_percent'], $feePercent);
        $I->click('#modalSave');

        $I->executeJS('javascript:showLimitsModal(\'Bank Transfer\', 2)');
        $I->click('Fees');
        $I->fillField(['name' => 'fee_amount'], $feeAmount * 2);
        $I->fillField(['name' => 'fee_percent'], $feePercent * 2);
        $I->click('#modalSave');
    }

    private function createInvoice($I, $clientEmail, $productKey, $amount, $fee, $partial = false)
    {
        $invoiceNumber = $I->fillInvoice($I, $clientEmail, $productKey);

        if ($partial) {
            $amount = ($partial * 2);
            $I->fillField('#partial', $partial);
        }

        $I->click('Mark Sent');
        //$I->see($invoiceNumber);
        //$I->see('Successfully created invoice');

        $clientId = $I->grabFromDatabase('contacts', 'client_id', ['email' => $clientEmail]);
        //$clientId = $I->grabFromDatabase('clients', 'id', ['name' => $clientEmail]);
        $invoiceId = $I->grabFromDatabase('invoices', 'id', ['client_id' => $clientId, 'invoice_number' => $invoiceNumber]);
        $invitationKey = $I->grabFromDatabase('invitations', 'invitation_key', ['invoice_id' => $invoiceId]);

        $balance = $partial ? ($amount - $partial) : 0;
        $this->createPayment($I, $invitationKey, $amount, $balance, $fee);

        return $invitationKey;
    }

    private function createPayment($I, $invitationKey, $amount, $balance, $fee)
    {
        $invoiceId = $I->grabFromDatabase('invitations', 'invoice_id', ['invitation_key' => $invitationKey]);

        // check we correctly remove/add back the gateway fee
        $I->amOnPage('/payment/' . $invitationKey . '/credit_card');
        $I->seeInDatabase('invoices', [
            'id' => $invoiceId,
            'amount' => ($amount + $fee),
        ]);

        $I->amOnPage('/payment/' . $invitationKey . '/bank_transfer');
        $I->seeInDatabase('invoices', [
            'id' => $invoiceId,
            'amount' => ($amount + $fee * 2),
        ]);

        $I->amOnPage('/view/' . $invitationKey);
        $I->seeInDatabase('invoices', [
            'id' => $invoiceId,
            'amount' => ($amount),
        ]);

        $I->createOnlinePayment($I, $invitationKey);

        $I->seeInDatabase('invoices', [
            'id' => $invoiceId,
            'amount' => ($amount + $fee),
            'balance' => $balance
        ]);
    }
}

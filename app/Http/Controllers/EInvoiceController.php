<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\EInvoice\ValidateEInvoiceRequest;
use App\Http\Requests\EInvoice\UpdateEInvoiceConfiguration;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;
use InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\CardAccountType\CardAccount;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\CardTypeCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode;

class EInvoiceController extends BaseController
{
    private array $einvoice_props = [
        'payment_means',
    ];

    public function validateEntity(ValidateEInvoiceRequest $request)
    {
        $el = new EntityLevel();

        $data = [];

        match($request->entity){
            'invoices' => $data = $el->checkInvoice($request->getEntity()),
            'clients' => $data = $el->checkClient($request->getEntity()),
            'companies' => $data = $el->checkCompany($request->getEntity()),
            default => $data['passes'] = false,
        };
        
        nlog($data);

        return response()->json($data, $data['passes'] ? 200 : 400);

    }

    public function configurations(UpdateEInvoiceConfiguration $request)
    {
     
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $pm = new PaymentMeans();

        $pmc = new PaymentMeansCode();
        $pmc->value = $request->input('payment_means.code', null);

        $ctc = new CardTypeCode();
        $ctc->value = $request->input('payment_means.card_type', null);
        $card_account = new CardAccount();
        $card_account->HolderName = $request->input('payment_means.cardholder_name', '');
        $card_account->CardTypeCode = $ctc;

        $instruct_note = new InstructionNote()
        $in
        $fib = new FinancialInstitutionBranch();
        $bic_id = new ID();
        $bic_id->value = $request->input('payment_means.bic', null);
        $fib->ID = $bic_id;
        $pfa = new PayeeFinancialAccount();
        $iban_id = new ID();
        $iban_id->value = $request->input('payment_means.iban', null);
        $pfa->ID = $iban_id;
        $pfa->Name = $request->input('payment_means.account_name', null);
        $pfa->FinancialInstitutionBranch = $fib;

        $pm->PayeeFinancialAccount = $pfa;
        $pm->PaymentMeansCode = $pmc;
        $pm->CardAccount = $card_account;
        $pm->InstructionNote = $request->input('payment_means.information', null);

        $einvoice->PaymentMeans[] = $pm;



        $stub = new \stdClass();
        $stub->Invoice = $einvoice;
   
    }

}

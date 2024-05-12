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

namespace App\Services\EDocument\Imports;

use App\Factory\ExpenseFactory;
use App\Models\Currency;
use App\Repositories\VendorRepository;
use App\Services\AbstractService;
use Exception;
use horstoeko\zugferd\ZugferdDocumentReader;

class ZugferdEDocument extends AbstractService
{
    public ZugferdDocumentReader $document;

    /**
     * @throws Exception
     */
    public function __construct(public object $tempdocument)
    {
        $this->document = ZugferdDocumentReader::readAndGuessFromContent($this->tempdocument);

    }

    /**
     * @throws Exception
     */
    public function run(): string
    {
        $user = auth()->user();

        $expense = ExpenseFactory::create($user->company()->id, $user->id);

        //
        $this->document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);
        nlog("Profile:               {$this->document->getProfileDefinitionParameter("name")}\r\n");
        nlog("Profile:               {$this->document->getProfileDefinitionParameter("altname")}\r\n");
        nlog("Document Type:         $documenttypecode\r\n");
        $expense->date = $documentdate;
        $expense->user_id = $user->id;
        $expense->company_id = $user->company()->id;
        $expense->public_notes = $documentno;
        $expense->currency_id = Currency::whereCode($invoiceCurrency);
        if ($taxCurrency != $invoiceCurrency){
            nlog("Unusal case in import happend: InvoiceCurrency unequal to taxcurrency");
        }
        $expense->uses_inclusive_taxes = false;

        $this->document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount);
        $expense->amount = $lineTotalAmount;
        $counter = 0;
        if ($this->document->firstDocumentTax()) {
            $counter += 1;
            do {
                $this->document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode);
                $expense->${"tax_amount$counter"} = $calculatedAmount;
                $expense->${"tax_rate$counter"} = $rateApplicablePercent;
            } while ($this->document->nextDocumentTax());
        }
        // TODO find vendor
        $vendors_registration = VendorRepository::class;
$vendor = $vendors_registration->findVendorByNumber($this->document->getDocumentSeller()->getGlobalID());
        return "";
    }
}


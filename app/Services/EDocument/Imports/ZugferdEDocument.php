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
use App\Models\Expense;
use App\Repositories\VendorRepository;
use App\Services\AbstractService;
use Exception;
use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferdvisualizer\ZugferdVisualizer;

class ZugferdEDocument extends AbstractService
{
    public ZugferdDocumentReader|string $document;

    /**
     * @throws Exception
     */
    public function __construct(public string $tempdocument, public string $documentname)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws Exception
     */
    public function run(): string
    {
        $user = auth()->user();
        $this->document = ZugferdDocumentReader::readAndGuessFromContent($this->tempdocument);
        $this->document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);
        nlog($documentno);
        nlog($documenttypecode);
        nlog($documentdate);
        nlog($invoiceCurrency);
        nlog($taxCurrency);
        nlog($documentname);
        nlog($documentlanguage);
        nlog($effectiveSpecifiedPeriod);
        $this->document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount);
        nlog($grandTotalAmount);
        nlog($duePayableAmount);

        $expenses = Expense::all();
        // Check if the document already exists as an expense
        $existingExpense = $expenses->first(function ($expense) use ($documentno, $grandTotalAmount, $documentdate) {
            return $expense->transaction_reference == $documentno && $expense->amount == $grandTotalAmount && $expense->date == $documentdate;
        });

        if ($existingExpense) {
            // The document already exists as an expense
            return $existingExpense;
        } else {
            // The document does not exist as an expense
            // Handle accordingly
            $visualizer = new ZugferdVisualizer($this->document);
            $visualizer->setDefaultTemplate();
            $visualizer->setPdfFontDefault("arial");
            $visualizer->setPdfPaperSize('A4-P');

            $expense = ExpenseFactory::create($user->company()->id, $user->id);
            $expense->date = $documentdate;
            $expense->user_id = $user->id;
            $expense->company_id = $user->company()->id;
            $expense->public_notes = $documentno;
            $expense->currency_id = Currency::whereCode($invoiceCurrency);
            $expense->documents()->create(["content" => $visualizer->renderPdf(), "filename" => $documentname."_visualizer.pdf"]);
            if ($taxCurrency != $invoiceCurrency){
                $expense->private_notes = "Tax currency is different from invoice currency";
            }
            $expense->uses_inclusive_taxes = false;
            $expense->amount = $lineTotalAmount;
            $counter = 1;
            if ($this->document->firstDocumentTax()) {
                do {
                    $this->document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode);
                    $expense->${"tax_amount$counter"} = $calculatedAmount;
                    $expense->${"tax_rate$counter"} = $rateApplicablePercent;
                    $counter++;
                } while ($this->document->nextDocumentTax());
            }
            $this->document->getDocumentSeller($name, $buyer_id, $buyer_description);
            $this->document->getDocumentSellerContact($person_name, $person_department, $contact_phone, $contact_fax, $contact_email);
            $this->document->getDocumentSellerTaxRegistration($taxtype);
            // TODO find vendor
            $vendors_registration = VendorRepository::class;
            $vendors = $vendors_registration::all();
            // Find vendor by vatid or email
            $vendor = $vendors->firstWhere('vatid', $taxtype) ?? $vendors->firstWhere('email', $contact_email);

            if ($vendor) {
                // Vendor found
                $expense->vendor_id = $vendor->id;
            } else {
                // Vendor not found
                // Handle accordingly
            }
            return $expense;
        }
    }
}


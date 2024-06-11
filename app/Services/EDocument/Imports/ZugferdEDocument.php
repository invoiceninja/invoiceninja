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
use App\Factory\VendorFactory;
use App\Jobs\Util\UploadFile;
use App\Models\Currency;
use App\Models\Document;
use App\Models\Expense;
use App\Repositories\VendorRepository;
use App\Services\AbstractService;
use App\Utils\TempFile;
use Exception;
use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferdvisualizer\ZugferdVisualizer;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ZugferdEDocument extends AbstractService {
    public ZugferdDocumentReader|string $document;

    /**
     * @throws Exception
     */
    public function __construct(public object $tempdocument, public string $documentname)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws Exception
     */
    public function run(): string
    {
        $user = auth()->user();
        $this->document = ZugferdDocumentReader::readAndGuessFromContent($this->tempdocument->file('documents')[0]->get());
        $this->document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $invoiceCurrency, $taxCurrency, $documentname, $documentlanguage, $effectiveSpecifiedPeriod);
        $this->document->getDocumentSummation($grandTotalAmount, $duePayableAmount, $lineTotalAmount, $chargeTotalAmount, $allowanceTotalAmount, $taxBasisTotalAmount, $taxTotalAmount, $roundingAmount, $totalPrepaidAmount);
        $expense = Expense::where('amount', $grandTotalAmount)->where("transaction_reference", $documentno)->where("date", $documentdate)->first();
        if (!$expense) {
            // The document does not exist as an expense
            // Handle accordingly
            $visualizer = new ZugferdVisualizer($this->document);
            $visualizer->setDefaultTemplate();
            $visualizer->setPdfFontDefault("arial");
            $visualizer->setPdfPaperSize('A4-P');

            $expense = ExpenseFactory::create($user->company()->id, $user->id);
            $expense->date = $documentdate;
            $expense->user_id = $user->id;
            $expense->company_id = $user->company->id;
            $expense->public_notes = $documentno;
            $expense->currency_id = Currency::whereCode($invoiceCurrency)->first()->id;
            $expense->save();

            (new UploadFile($this->tempdocument->file('documents'), UploadFile::DOCUMENT, $user, $expense->company, $expense, null, false))->handle();
            $uploaded_file = TempFile::UploadedFileFromRaw($visualizer->renderPdf(), $documentno."_visualiser.pdf", "application/pdf");
            (new UploadFile($uploaded_file, UploadFile::DOCUMENT, $user, $expense->company, $expense, null, false))->handle();
            $expense->save();
            if ($taxCurrency != $invoiceCurrency) {
                $expense->private_notes = "Tax currency is different from invoice currency";
            }
            $expense->uses_inclusive_taxes = false;
            $expense->amount = $lineTotalAmount;
            $counter = 1;
            if ($this->document->firstDocumentTax()) {
                do {
                    $this->document->getDocumentTax($categoryCode, $typeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasisAmount, $allowanceChargeBasisAmount, $taxPointDate, $dueDateTypeCode);
                    $expense->{"tax_amount$counter"} = $calculatedAmount;
                    $expense->{"tax_rate$counter"} = $rateApplicablePercent;
                    $counter++;
                } while ($this->document->nextDocumentTax());
            }
            $this->document->getDocumentSeller($name, $buyer_id, $buyer_description);
            $this->document->getDocumentSellerContact($person_name, $person_department, $contact_phone, $contact_fax, $contact_email);
            $this->document->getDocumentSellerTaxRegistration($taxtype);
            $taxid = null;
            if (array_key_exists("VA", $taxtype)) {
                $taxid = $taxtype["VA"];
            }
            // TODO find vendor
            $vendors_registration = new VendorRepository();
            $vendors = $vendors_registration->all();
            // Find vendor by vatid or email
            $vendor = $vendors->firstWhere('vatid', $taxid) ?? $vendors->firstWhere('email', $contact_email);

            if ($vendor) {
                // Vendor found
                $expense->vendor_id = $vendor->id;
            } else {
                $vendor = VendorFactory::create($user->company()->id, $user->id);
                $vendor->name = $name;
                if ($taxid != null) {
                    $vendor->vatid = $taxid;
                }
                $vendor->email = $contact_email;

                $vendor->save();
                $expense->vendor_id = $vendor->id;
                // Vendor not found
                // Handle accordingly
            }
            $expense->transaction_reference = $documentno;
            $expense->save();
            }
    return $expense;
    }
}


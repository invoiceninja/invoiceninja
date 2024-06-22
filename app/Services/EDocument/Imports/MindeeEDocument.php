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
use App\Models\Country;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Vendor;
use App\Services\AbstractService;
use App\Utils\TempFile;
use App\Utils\Traits\SavesDocuments;
use Exception;
use Mindee\Client;
use Mindee\Product\Invoice\InvoiceV4;
use Illuminate\Http\UploadedFile;

class MindeeEDocument extends AbstractService
{
    use SavesDocuments;

    /**
     * @throws Exception
     */
    public function __construct(public UploadedFile $file)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws Exception
     */
    public function run(): Expense
    {
        $user = auth()->user();

        $api_key = config('services.mindee.api_key');

        if (!$api_key)
            throw new Exception('Mindee API key not configured');

        // check global contingent
        // TODO: add contingent for each company


        $mindeeClient = new Client($api_key);


        // Load a file from disk
        $inputSource = $mindeeClient->sourceFromFile($this->file);

        // Parse the file
        $result = $mindeeClient->parse(InvoiceV4::class, $inputSource);

        /** @var \Mindee\Product\Invoice\InvoiceV4Document $prediction */
        $prediction = $result->document->inference->prediction;

        if ($prediction->documentType !== 'INVOICE')
            throw new Exception('Unsupported document type');

        $grandTotalAmount = $prediction->totalAmount->value;
        $documentno = $prediction->invoiceNumber->value;
        $documentdate = $prediction->date->value;
        $invoiceCurrency = $prediction->locale->currency;
        $country = $prediction->locale->country;

        $expense = Expense::where('amount', $grandTotalAmount)->where("transaction_reference", $documentno)->whereDate("date", $documentdate)->first();
        if (empty($expense)) {
            // The document does not exist as an expense
            // Handle accordingly
            $expense = ExpenseFactory::create($user->company()->id, $user->id);
            $expense->date = $documentdate;
            $expense->user_id = $user->id;
            $expense->company_id = $user->company->id;
            $expense->public_notes = $documentno;
            $expense->currency_id = Currency::whereCode($invoiceCurrency)->first()->id;
            $expense->save();

            $this->saveDocument($this->file, $expense);
            $expense->saveQuietly();

            // if ($taxCurrency && $taxCurrency != $invoiceCurrency) {
            //     $expense->private_notes = ctrans("texts.tax_currency_mismatch");
            // }
            $expense->uses_inclusive_taxes = True;
            $expense->amount = $grandTotalAmount;
            $counter = 1;
            foreach ($prediction->taxes as $taxesElem) {
                $expense->{"tax_amount$counter"} = $taxesElem->amount;
                $expense->{"tax_rate$counter"} = $taxesElem->rate;
                $counter++;
            }
            $taxid = null;
            if (array_key_exists("VA", $taxtype)) {
                $taxid = $taxtype["VA"];
            }
            $vendor = Vendor::where('email', $prediction->supplierEmail)->first();

            if (!empty($vendor)) {
                // Vendor found
                $expense->vendor_id = $vendor->id;
            } else {
                $vendor = VendorFactory::create($user->company()->id, $user->id);
                $vendor->name = $prediction->supplierName;
                $vendor->email = $prediction->supplierEmail;

                $vendor->currency_id = Currency::whereCode($invoiceCurrency)->first()->id;
                $vendor->phone = $prediction->supplierPhoneNumber;
                // $vendor->address1 = $address_1; // TODO: we only have the full address string
                // $vendor->address2 = $address_2;
                // $vendor->city = $city;
                // $vendor->postal_code = $postcode;
                $vendor->country_id = Country::where('iso_3166_2', $country)->first()->id || Country::where('iso_3166_3', $country)->first()->id; // could be 2 or 3 length

                $vendor->save();
                $expense->vendor_id = $vendor->id;
            }
            $expense->transaction_reference = $documentno;
        } else {
            // The document exists as an expense
            // Handle accordingly
            nlog("Document already exists");
            $expense->private_notes = $expense->private_notes . ctrans("texts.edocument_import_already_exists", ["date" => time()]);
        }
        $expense->save();
        return $expense;
    }
}


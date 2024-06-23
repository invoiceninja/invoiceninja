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
use Cache;
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
        $this->checkLimits();

        // perform parsing
        $mindeeClient = new Client($api_key);
        $inputSource = $mindeeClient->sourceFromFile($this->file);
        $result = $mindeeClient->parse(InvoiceV4::class, $inputSource);
        $this->incrementRequestCounts();

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

            $this->saveDocuments([
                $this->file,
                TempFile::UploadedFileFromRaw(strval($result->document), $documentno . "_mindee_orc_result.txt", "text/plain")
            ], $expense);
            $expense->saveQuietly();

            $expense->uses_inclusive_taxes = True;
            $expense->amount = $grandTotalAmount;
            $counter = 1;
            foreach ($prediction->taxes as $taxesElem) {
                $expense->{"tax_amount$counter"} = $taxesElem->amount;
                $expense->{"tax_rate$counter"} = $taxesElem->rate;
                $counter++;
            }

            $vendor = Vendor::where('email', $prediction->supplierEmail)->first();

            if (!empty($vendor)) {
                // Vendor found
                $expense->vendor_id = $vendor->id;
            } else {
                $vendor = VendorFactory::create($user->company()->id, $user->id);
                $vendor->name = $prediction->supplierName;
                $vendor->email = $prediction->supplierEmail;

                $vendor->currency_id = Currency::whereCode($invoiceCurrency)->first()?->id;
                $vendor->phone = $prediction->supplierPhoneNumber;
                // $vendor->address1 = $address_1; // TODO: we only have the full address string from mindee returned
                // $vendor->address2 = $address_2;
                // $vendor->city = $city;
                // $vendor->postal_code = $postcode;
                $vendor->country_id = Country::where('iso_3166_2', $country)->first()?->id || Country::where('iso_3166_3', $country)->first()?->id || null; // could be 2 or 3 length

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

    private function checkLimits()
    {
        $user = auth()->user();

        Cache::add('mindeeTotalDailyRequests', 0, now()->endOfDay());
        Cache::add('mindeeTotalMonthlyRequests', 0, now()->endOfMonth());
        Cache::add('mindeeAccountDailyRequests' . $user->company->account->id, 0, now()->endOfDay());
        Cache::add('mindeeAccountMonthlyRequests' . $user->company->account->id, 0, now()->endOfMonth());
        if (config('services.mindee.daily_limit') != 0 && Cache::get('mindeeTotalDailyRequests') > config('services.mindee.daily_limit'))
            throw new Exception('Mindee daily limit reached');
        if (config('services.mindee.monthly_limit') != 0 && Cache::get('mindeeTotalMonthlyRequests') > config('services.mindee.monthly_limit'))
            throw new Exception('Mindee monthly limit reached');
        if (config('services.mindee.account_daily_limit') != 0 && Cache::get('mindeeAccountDailyRequests' . $user->company->account->id) > config('services.mindee.account_daily_limit'))
            throw new Exception('Mindee daily limit reached for account: ' . $user->company->account->id);
        if (config('services.mindee.account_monthly_limit') != 0 && Cache::get('mindeeAccountMonthlyRequests' . $user->company->account->id) > config('services.mindee.account_monthly_limit'))
            throw new Exception('Mindee monthly limit reached for account: ' . $user->company->account->id);
    }

    private function incrementRequestCounts()
    {
        $user = auth()->user();

        Cache::increment('mindeeTotalDailyRequests');
        Cache::increment('mindeeTotalMonthlyRequests');
        Cache::increment('mindeeAccountDailyRequests' . $user->company->account->id);
        Cache::increment('mindeeAccountMonthlyRequests' . $user->company->account->id);
    }
}


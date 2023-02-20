<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ProjectFactory;
use App\Factory\VendorFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Utils\Number;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Class BaseTransformer.
 */
class BaseTransformer
{
    protected $company;

    public function __construct($company)
    {
        $this->company = $company;
    }

    public function parseDate($date)
    {
        try {
            $parsed_date = Carbon::parse($date);

            return $parsed_date->format('Y-m-d');
        } catch(\Exception $e) {
            $parsed_date = date('Y-m-d', strtotime($date));

            if ($parsed_date == '1970-01-01') {
                return now()->format('Y-m-d');
            }

            return $parsed_date;
        }
    }

    public function getInvoiceTypeId($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? (string)$data[$field] : '1';
    }

    public function getNumber($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? (int)$data->$field : 0;
    }

    public function getString($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? trim($data[$field]) : '';
    }

    public function getValueOrNull($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? $data[$field] : null;
    }

    public function getCurrencyByCode($data, $key = 'client.currency_id')
    {
        $code = array_key_exists($key, $data) ? $data[$key] : false;

        $currencies = Cache::get('currencies');

        $currency = $currencies
            ->filter(function ($item) use ($code) {
                return $item->code == $code;
            })
            ->first();

        return $currency
            ? $currency->id
            : $this->company->settings->currency_id;
    }

    public function getClient($client_name, $client_email)
    {
        if (! empty($client_name)) {
            $client_id_search = Client::where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->where('id_number', $client_name);

            if ($client_id_search->count() >= 1) {
                return $client_id_search->first()->id;
            }

            $client_name_search = Client::where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                    strtolower(str_replace(' ', '', $client_name)),
                ]);

            if ($client_name_search->count() >= 1) {
                return $client_name_search->first()->id;
            }
        }
        if (! empty($client_email)) {
            $contacts = ClientContact::whereHas('client', function ($query) {
                $query->where('is_deleted', false);
            })
            ->where('company_id', $this->company->id)
            ->where('email', $client_email);

            if ($contacts->count() >= 1) {
                return $contacts->first()->client_id;
            }
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////////////////
    /**
     * @param $name
     *
     * @return bool
     */
    public function hasClient($name)
    {
        return Client::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->exists();
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasVendor($name)
    {
        return Vendor::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->exists();
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasProject($name)
    {
        return Project::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->exists();
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasProduct($key)
    {
        return Product::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`product_key`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $key)),
            ])
            ->exists();
    }

    /**
     * @param $data
     * @param $field
     *
     * @return float
     */
    public function getFloat($data, $field)
    {
        if (array_key_exists($field, $data)) {
            //$number = preg_replace('/[^0-9-.]+/', '', $data[$field]);
            return Number::parseStringFloat($data[$field]);
        } else {
            //$number = 0;
            return 0;
        }

        // return Number::parseFloat($number);
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getClientId($name)
    {
        $client = Client::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $client ? $client->id : null;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getProduct($key)
    {
        $product = Product::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`product_key`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $key)),
            ])
            ->first();

        return $product;
    }

    /**
     * @param $email
     *
     * @return ?Contact
     */
    public function getContact($email)
    {
        $contact = ClientContact::where('company_id', $this->company->id)
            ->whereRaw("LOWER(REPLACE(`email`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $email)),
            ])
            ->first();

        if (! $contact) {
            return null;
        }

        return $contact;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getCountryId($name)
    {
        if (strlen($name) == 2) {
            return $this->getCountryIdBy2($name);
        }

        $country = Country::whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
            strtolower(str_replace(' ', '', $name)),
        ])->first();

        return $country ? $country->id : null;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getCountryIdBy2($name)
    {
        return Country::where('iso_3166_2', $name)->exists()
            ? Country::where('iso_3166_2', $name)->first()->id
            : null;
    }

    /**
     * @param $name
     *
     * @return int
     */
    public function getTaxRate($name)
    {
        $name = strtolower(trim($name));

        $tax_rate = TaxRate::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $tax_rate ? $tax_rate->rate : 0;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getTaxName($name)
    {
        $name = strtolower(trim($name));

        $tax_rate = TaxRate::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $tax_rate ? $tax_rate->name : '';
    }

    /**
     * @param $date
     * @param string $format
     * @param mixed  $data
     * @param mixed  $field
     *
     * @return null
     */
    public function getDate($data, $field)
    {
        if ($date = data_get($data, $field)) {
            try {
                $date = new Carbon($date);
            } catch (\Exception $e) {
                // if we fail to parse return blank
                $date = false;
            }
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * @param $number
     *
     * @return ?string
     */
    public function getInvoiceNumber($number)
    {
        return $number ? ltrim(trim($number), '0') : null;
    }

    /**
     * @param $invoice_number
     *
     * @return int|null
     */
    public function getInvoiceId($invoice_number)
    {
        $invoice = Invoice::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->first();

        return $invoice ? $invoice->id : null;
    }

    /**
     * @param $invoice_number
     *
     * @return bool
     */
    public function hasInvoice($invoice_number)
    {
        return Invoice::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->exists();
    }

    /**     *
     * @return bool
     */
    public function hasExpense($expense_number)
    {
        return Expense::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $expense_number)),
            ])
            ->exists();
    }

    /**
     * @param $quote_number
     *
     * @return bool
     */
    public function hasQuote($quote_number)
    {
        return Quote::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $quote_number)),
            ])
            ->exists();
    }

    /**
     * @param $invoice_number
     *
     * @return int|null
     */
    public function getInvoiceClientId($invoice_number)
    {
        $invoice = Invoice::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->first();

        return $invoice ? $invoice->client_id : null;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getVendorId($name)
    {
        $vendor = Vendor::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $vendor ? $vendor->id : null;
    }

    public function getVendorIdOrCreate($name)
    {
        if (empty($name)) {
            return null;
        }

        $vendor = $this->getVendorId($name);

        if ($vendor) {
            return $vendor;
        }

        $vendor = VendorFactory::create($this->company->id, $this->company->owner()->id);
        $vendor->name = $name;
        $vendor->save();

        return $vendor->id;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getExpenseCategoryId($name)
    {
        $ec = ExpenseCategory::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $ec ? $ec->id : null;
    }

    public function getOrCreateExpenseCategry($name)
    {
        if (empty($name)) {
            return null;
        }

        $ec = $this->getExpenseCategoryId($name);

        if ($ec) {
            return $ec;
        }

        $expense_category = ExpenseCategoryFactory::create($this->company->id, $this->company->owner()->id);
        $expense_category->name = $name;
        $expense_category->save();

        return $expense_category->id;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getProjectId($name, $clientId = null)
    {
        $project = Project::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $project ? $project->id : $this->createProject($name, $clientId);
    }

    private function createProject($name, $clientId)
    {
        $project = ProjectFactory::create($this->company->id, $this->company->owner()->id);
        $project->name = $name;

        if ($clientId) {
            $project->client_id = $clientId;
        }

        $project->saveQuietly();

        return $project->id;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getPaymentTypeId($name)
    {
        $pt = PaymentType::whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
            strtolower(str_replace(' ', '', $name)),
        ])->first();

        return $pt ? $pt->id : null;
    }
}

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

use App\Models\Vendor;
use App\Models\Company;
use App\Models\Country;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Factory\VendorContactFactory;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;

use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\SavesDocuments;

class UblEDocument extends AbstractService
{
    use SavesDocuments;

    /**
     * @throws \Throwable
     */
    public function __construct(public UploadedFile $file, public Company $company)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws \Throwable
     */
    public function run(): \App\Models\Expense
    {

        //parse doc
        // try{
            $e = new EInvoice();
            $invoice = $e->decode('Peppol', $this->file->get(), 'xml');

            return $this->buildAndSaveExpense($invoice);

        // }
        // catch(\Throwable $e){
        //     return $e->getMessage();
        // }

    }

    private function buildAndSaveExpense(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Expense
    {

        $vendor = $this->findOrCreateVendor($invoice);


        /** @var \App\Models\Expense $expense */
        $expense = ExpenseFactory::create($this->company->id, $this->company->owner()->id);

        return $expense;


    }

    private function findOrCreateVendor(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Vendor
    {
        $asp = $invoice->AccountingSupplierParty;
        
        $vat_number = $asp->Party->PartyTaxScheme->CompanyID ?? false;
        $id_number = $asp->Party->PartyIdentification->ID ?? false;
        $routing_id = $asp->Party->EndpointID ?? false;
        $vendor_name = $asp->Party->PartyName->Name ?? false;

        $vendor = Vendor::query()
                    ->where('company_id', $this->company->id)
                    ->where(function ($q) use ($vat_number, $routing_id, $id_number, $vendor_name){

                        $q->when($routing_id, function ($q) use ($routing_id){
                            $q->where('routing_id', $routing_id);
                        })
                        ->when($vat_number, function ($q) use ($vat_number){
                            $q->orWhere('vat_number', $vat_number);
                        })
                        ->when($id_number, function ($q) use ($id_number){
                            $q->orWhere('id_number', $id_number);
                        })
                        ->when($vendor_name, function ($q) use ($vendor_name){
                            $q->orWhere('name', $vendor_name);
                        });

                    })->first();

        return $vendor ?? $this->newVendor($invoice);
    }

    private function newVendor(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Vendor
    {
        $vendor = VendorFactory::create($this->company->id, $this->company->owner()->id);

        $data = [
            'name' => data_get($invoice, 'AccountingSupplierParty.Party.PartyName.Name', ''),
            'routing_id' => data_get($invoice, 'AccountingSupplierParty.Party.EndpointID', ''),
            'vat_number' => data_get($invoice, 'AccountingSupplierParty.Party.PartyTaxScheme.CompanyID', ''),
            'address1' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.StreetName',''),
            'address2' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.AdditionalStreetName',''),
            'city' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.CityName',''),
            'state' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity',''),
            'postal_code' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.PostalZone',''),
            'country_id' => $this->resovelCountry(data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.Country.IdentificationCode','')),
        ];

        $vendor->fill($data);
        $vendor->save();

        if(data_get($invoice, 'AccountingSupplierParty.Party.Contact',false))
        {
            $vc = VendorContactFactory::create($this->company->id, $this->company->owner()->id);
            $vc->vendor_id = $vendor->id;
            $vc->first_name = data_get($invoice, 'AccountingSupplierParty.Party.Contact.Name','');
            $vc->phone = data_get($invoice, 'AccountingSupplierParty.Party.Contact.Telephone', '');
            $vc->email = data_get($invoice, 'AccountingSupplierParty.Party.Contact.ElectronicMail', '');
            $vc->save();
        }

        return $vendor->fresh();

    }

    private function resolveCountry(?string $iso_country_code): int
    {
        return Country::query()
                        ->where('iso_3166_2', $iso_country_code)
                        ->orWhere('iso_3166_3', $iso_country_code)
                        ->first() ?? (int)$this->company->settings->country_id;
    }
}
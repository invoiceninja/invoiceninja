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

namespace App\Services\EDocument\Standards\Validation\Peppol;

use App\Exceptions\PeppolValidationException;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Support\Facades\App;

class EntityLevel
{
    private array $client_fields = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country_id',
    ];

    private array $company_settings_fields = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country_id',
    ];

    private array $company_fields = [
        // 'legal_entity_id',
        // 'vat_number IF NOT an individual
    ];

    private array $invoice_fields = [
        // 'number',
    ];

    private array $errors = [];

    public function __construct()
    {
    }

    private function init(string $locale): self
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($locale);

        return $this;

    }

    public function checkClient(Client $client): array
    {
        $this->init($client->locale());
        $this->errors['client'] = $this->testClientState($client);
        $this->errors['passes'] = count($this->errors['client']) == 0;

        return $this->errors;

    }

    public function checkCompany(Company $company): array
    {

        $this->init($company->locale());
        $this->errors['company'] = $this->testCompanyState($company);
        $this->errors['passes'] = count($this->errors['company']) == 0;

        return $this->errors;

    }

    public function checkInvoice(Invoice $invoice): array
    {
        $this->init($invoice->client->locale());

        $this->errors['invoice'] = [];
        $this->errors['client'] = $this->testClientState($invoice->client);
        $this->errors['company'] = $this->testCompanyState($invoice->client); // uses client level settings which is what we want

        $p = new Peppol($invoice);

        try{
            $p->run()->toXml();
        }
        catch(PeppolValidationException $e) {

            $this->errors['invoice'] = ['field' => $e->getInvalidField()];

        };

        $this->errors['passes'] = count($this->errors['invoice']) == 0 && count($this->errors['client']) == 0 && count($this->errors['company']) == 0;

        return $this->errors;

    }

    private function testClientState(Client $client): array
    {

        $errors = [];

        foreach($this->client_fields as $field)
        {

            if($this->validString($client->{$field}))
                continue;

            if($field == 'country_id' && $client->country_id >=1)
                continue;

            $errors[] = ['field' => ctrans("texts.{$field}")];

        }

        //If not an individual, you MUST have a VAT number
        if ($client->classification != 'individual' && !$this->validString($client->vat_number)) {
            $errors[] = ['field' => ctrans("texts.vat_number")];
        }

        return $errors;

    }

    private function testCompanyState(mixed $entity): array
    {
        
        $client = false;
        $vendor = false;
        $settings_object = false;
        $company =false;

        if($entity instanceof Client){
            $client = $entity;
            $company = $entity->company;
            $settings_object = $client;
        }
        elseif($entity instanceof Company){
            $company = $entity;
            $settings_object = $company;    
        }
        elseif($entity instanceof Vendor){
            $vendor = $entity;    
            $company = $entity->company;
            $settings_object = $company;
        }
        elseif($entity instanceof Invoice || $entity instanceof Credit || $entity instanceof Quote){
            $client = $entity->client;
            $company = $entity->company;
            $settings_object = $entity->client;
        }
        elseif($entity instanceof PurchaseOrder){
            $vendor = $entity->vendor;
            $company = $entity->company;
            $settings_object = $company;
        }

        $errors = [];

        foreach($this->company_settings_fields as $field)
        {

            if($this->validString($settings_object->getSetting($field)))
                continue;
    
            $errors[] = ['field' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if(!is_int($company->legal_entity_id))
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];

        //If not an individual, you MUST have a VAT number
        if($company->getSetting('classification') != 'individual' && !$this->validString($company->getSetting('vat_number')))
        {
            $errors[] = ['field' => ctrans("texts.vat_number")];
        }

        // foreach($this->company_fields as $field)
        // {

        // }

        return $errors;

    }

    // private function testInvoiceState($entity): array
    // {
    //     $errors = [];

    //     foreach($this->invoice_fields as $field)
    //     {

    //     }

    //     return $errors;
    // }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string): bool
    {
        return iconv_strlen($string) >= 1;
    }

}
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

use App\Models\Client;
use App\Models\Invoice;
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

    public function __invoke($entity): array
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($entity->company->locale());

        $this->errors['status'] = false;
        $this->errors['client'] = $entity->client ? $this->testClientState($entity) : [];
        $this->errors['company'] = $this->testCompanyState($entity->company);
        $this->errors['invoice'] = $entity instanceof Invoice ? $this->testInvoiceState($entity) : [];
        
        // $this->errors['vendor']= $entity->client ? $this->testClientState($entity) : [];

        if(
            count($this->errors['client']) == 0 && 
            count($this->errors['company']) == 0
        ){
            $this->errors['status'] = true;
        }

        return $this->errors;

    }

    private function testClientState($entity): array
    {

        $errors = [];

        foreach($this->client_fields as $field)
        {

            if($this->validString($entity->client->{$field}))
                continue;

            $errors[] = ['field' => ctrans("texts.{$field}")];

        }

        //If not an individual, you MUST have a VAT number
        if ($client->classification != 'individual' && !$this->validString($client->vat_number)) {
            $errors[] = ['field' => ctrans("texts.vat_number")];
        }

        return $errors;

    }

    private function testCompanyState($entity): array
    {

        $settings_object = $entity->client ? $entity->client : $entity->company;
        $errors = [];

        foreach($this->company_settings_fields as $field)
        {
            if($this->validString($settings_object->getSetting($field)))
                continue;
    
            $errors[] = ['field' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if(!is_int($entity->company->legal_entity_id))
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];

        //If not an individual, you MUST have a VAT number
        if($settings_object->getSetting('classification') != 'individual' && !$this->validString($settings_object->getSetting('vat_number')))
        {
            $errors[] = ['field' => ctrans("texts.vat_number")];
        }

        // foreach($this->company_fields as $field)
        // {

        // }

        return $errors;

    }

    private function testInvoiceState(): array
    {
        $errors = [];

        foreach($this->invoice_fields as $field)
        {

        }

        return $errors;
    }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string)
    {
        return iconv_strlen($string) > 1;
    }

}
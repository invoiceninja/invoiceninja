<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\Pdf\PdfService;
use App\Utils\Traits\MakesHash;

class PdfConfiguration
{
    use MakesHash;

    public Design $design;

    public ?Client $client;

    public ?ClientContact $contact;

    public ?Vendor $vendor;

    public ?VendorContact $vendor_contact;

    public object $settings;

    public $settings_object;

    public $entity;

    public string $entity_string;

    public $service;

    public array $pdf_variables;

    public function __construct(PdfService $service)
    {

        $this->service = $service;
        
    }

    public function init()
    {

        $this->setEntityType()
             ->setEntityProperties()
             ->setPdfVariables()
             ->setDesign();

        return $this;

    }

    private function setPdfVariables() :self
    {

        $default = (array) CompanySettings::getEntityVariableDefaults();
        $variables = $this->service->company->pdf_variables;

        foreach ($default as $property => $value) {
            if (array_key_exists($property, $variables)) {
                continue;
            }

            $variables[$property] = $value;
        }

        $this->pdf_variables = $variables;

        return $this;

    }

    private function setEntityType()
    {

        if ($this->service->invitation instanceof InvoiceInvitation) {
            $this->entity = $this->service->invitation->invoice;
            $this->entity_string = 'invoice';
        } elseif ($this->service->invitation instanceof QuoteInvitation) {
            $this->entity = $this->service->invitation->quote;
            $this->entity_string = 'quote';
        } elseif ($this->service->invitation instanceof CreditInvitation) {
            $this->entity = $this->service->invitation->credit;
            $this->entity_string = 'credit';
        } elseif ($this->service->invitation instanceof RecurringInvoiceInvitation) {
            $this->entity = $this->service->invitation->recurring_invoice;
            $this->entity_string = 'recurring_invoice';
        } elseif ($this->service->invitation instanceof PurchaseOrderInvitation) {
            $this->entity = $this->service->invitation->purchase_order;
            $this->entity_string = 'purchase_order';
        } else {
            throw new \Exception('Unable to resolve entity', 500);
        }

        return $this;
    }

    private function setEntityProperties()
    {
         $entity_design_id = '';

        if ($this->entity instanceof Invoice) {

            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;

        } elseif ($this->entity instanceof Quote) {

            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->quote_filepath($this->service->invitation);
            $this->entity_design_id = 'quote_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;

        } elseif ($this->entity instanceof Credit) {

            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->credit_filepath($this->service->invitation);
            $this->entity_design_id = 'credit_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;

        } elseif ($this->entity instanceof RecurringInvoice) {

            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->recurring_invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;

        } elseif ($this->entity instanceof PurchaseOrder) {

            $this->vendor = $this->entity->vendor;
            $this->vendor_contact = $this->service->invitation->contact;
            $this->path = $this->vendor->purchase_order_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->entity_design_id = 'purchase_order_design_id';
            $this->settings = $this->vendor->getMergedSettings();
            $this->settings_object = $this->client;

        } 
        else
            throw new \Exception('Unable to resolve entity properties type', 500);

        $this->path = $this->path.$this->entity->numberFormatter().'.pdf';

        return $this;
    }

    private function setDesign()
    {

        $design_id = $this->entity->design_id ? : $this->decodePrimaryKey($this->settings_object->getSetting($this->entity_design_id));
            
        $this->design = Design::find($design_id ?: 2);

        return $this;

    }

}
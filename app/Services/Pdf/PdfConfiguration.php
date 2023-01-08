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
use App\Models\Currency;
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
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;

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

    public array $pdf_variables;

    public Currency $currency;

    /**
     * The parent object of the currency 
     * 
     * @var App\Models\Client | App\Models\Vendor
     * 
     */
    public Client | Vendor $currency_entity;

    public function __construct(public PdfService $service){}

    public function init(): self
    {

        $this->setEntityType()
             ->setPdfVariables()
             ->setDesign()
             ->setCurrency()
             ->setLocale();

        return $this;

    }

    private function setLocale(): self
    {

        App::forgetInstance('translator');

        $t = app('translator');

        App::setLocale($this->settings_object->locale());

        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;

    }

    private function setCurrency(): self
    {

        $this->currency = $this->client ? $this->client->currency() : $this->vendor->currency();

        $this->currency_entity = $this->client ? $this->client : $this->vendor;

        return $this;

    }

    private function setPdfVariables() :self
    {

        $default = (array) CompanySettings::getEntityVariableDefaults();

        $variables = (array)$this->service->company->settings->pdf_variables;

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

        $entity_design_id = '';

        if ($this->service->invitation instanceof InvoiceInvitation) {
            $this->entity = $this->service->invitation->invoice;
            $this->entity_string = 'invoice';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
        } elseif ($this->service->invitation instanceof QuoteInvitation) {
            $this->entity = $this->service->invitation->quote;
            $this->entity_string = 'quote';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->quote_filepath($this->service->invitation);
            $this->entity_design_id = 'quote_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
        } elseif ($this->service->invitation instanceof CreditInvitation) {
            $this->entity = $this->service->invitation->credit;
            $this->entity_string = 'credit';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->credit_filepath($this->service->invitation);
            $this->entity_design_id = 'credit_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
        } elseif ($this->service->invitation instanceof RecurringInvoiceInvitation) {
            $this->entity = $this->service->invitation->recurring_invoice;
            $this->entity_string = 'recurring_invoice';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->recurring_invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
        } elseif ($this->service->invitation instanceof PurchaseOrderInvitation) {
            $this->entity = $this->service->invitation->purchase_order;
            $this->entity_string = 'purchase_order';
            $this->vendor = $this->entity->vendor;
            $this->vendor_contact = $this->service->invitation->contact;
            $this->path = $this->vendor->purchase_order_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->entity_design_id = 'purchase_order_design_id';
            $this->settings = $this->vendor->company->settings;
            $this->settings_object = $this->vendor;
            $this->client = null;
        } else {
            throw new \Exception('Unable to resolve entity', 500);
        }

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
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

namespace App\Http\Livewire;

use App\Utils\Number;
use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Models\QuoteInvitation;
use App\Utils\VendorHtmlEngine;
use App\Models\CreditInvitation;
use App\Services\Pdf\PdfBuilder;
use App\Services\Pdf\PdfService;
use App\Models\InvoiceInvitation;
use App\Services\Pdf\PdfDesigner;
use App\Services\Pdf\PdfConfiguration;
use App\Models\PurchaseOrderInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;

class PdfSlot extends Component
{
    public $invitation;

    public $db;

    public $entity;

    public $pdf;

    public $url;
    
    private $settings;

    private $html_variables;

    private $entity_type;

    protected $listeners = ['viewportChanged' => 'getPdf'];

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function getPdf()
    {        
        $this->pdf = $this->entity->fullscreenPdfViewer($this->invitation);
    }

    public function downloadPdf()
    {
        
        $file_name = $this->entity->numberFormatter().'.pdf';

        if($this->entity instanceof \App\Models\PurchaseOrder)
            $file = (new CreatePurchaseOrderPdf($this->invitation, $this->invitation->company->db))->rawPdf();
        else
            $file = (new \App\Jobs\Entity\CreateRawPdf($this->invitation, $this->invitation->company->db))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function render()
    {
        $this->entity_type = $this->resolveEntityType();

        $this->settings = $this->entity->client ? $this->entity->client->getMergedSettings() : $this->entity->company->settings;

        $this->html_variables = $this->entity->client ?
                            (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                            (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        return render('components.livewire.pdf-slot', [
            'invitation' => $this->invitation,
            'entity' => $this->entity,
            'data' => $this->invitation->company->settings,
            'entity_type' => $this->entity_type,
            'products' => $this->getProducts(),
            'services' => $this->getServices(),
            'amount' => Number::formatMoney($this->entity->amount, $this->entity->client ?: $this->entity->vendor),
            'balance' => Number::formatMoney($this->entity->balance, $this->entity->client ?: $this->entity->vendor),
            'company_details' => $this->getCompanyDetails(),
            'company_address' => $this->getCompanyAddress(),
            'entity_details' => $this->getEntityDetails(),
            'user_details' => $this->getUserDetails(),
            'user_name' => $this->getUserName(),

        ]);
    }

    private function convertVariables($string): string
    {

        $html = strtr($string, $this->html_variables['labels']);
        $html = strtr($html, $this->html_variables['values']);

        return $html;

    }

    private function getCompanyAddress()
    {
        
        $company_address = "";

        foreach($this->settings->pdf_variables->company_address as $variable) {
            $company_address .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_address);

    }

    private function getCompanyDetails()
    {
        $company_details = "";

        foreach($this->settings->pdf_variables->company_details as $variable) {
            $company_details .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_details);
  
    }

    private function getEntityDetails()
    {
        $entity_details = "";

        if($this->entity_type == 'invoice' || $this->entity_type == 'recurring_invoice') {
            foreach($this->settings->pdf_variables->invoice_details as $variable) 
                $entity_details .= "<div class='flex px-3 block'><p class= w-36 block'>{$variable}_label</p><p class='pl-1 w-36 block entity-field'>{$variable}</p></div>";
    
        }
        elseif($this->entity_type == 'quote'){
            foreach($this->settings->pdf_variables->quote_details as $variable)
                $entity_details .= "<div class='flex px-3 block'><p class= w-36 block'>{$variable}_label</p><p class='pl-1 w-36 block entity-field'>{$variable}</p></div>";
        }
        elseif($this->entity_type == 'credit') {
            foreach($this->settings->pdf_variables->credit_details as $variable)
                $entity_details .= "<div class='flex px-3 block'><p class= w-36 block'>{$variable}_label</p><p class='pl-1 w-36 block entity-field'>{$variable}</p></div>";
        }
        elseif($this->entity_type == 'purchase_order'){
            foreach($this->settings->pdf_variables->purchase_order_details as $variable)
                $entity_details .= "<div class='flex px-3 block'><p class= w-36 block'>{$variable}_label</p><p class='pl-1 w-36 block entity-field'>{$variable}</p></div>";
        }
            
        return $this->convertVariables($entity_details);

    }

    private function getUserName()
    {
        $name = ctrans('texts.details');

        if($this->entity_type == 'purchase_order' && isset($this->settings->pdf_variables->vendor_details[0])) {
            $name = $this->settings->pdf_variables->vendor_details[0];

        } elseif(isset($this->settings->pdf_variables->client_details[0])) {

            $name = $this->settings->pdf_variables->client_details[0];
        }
                
        return $this->convertVariables($name);

    }

    private function getUserDetails()
    {
        $user_details = "";

        if($this->entity_type == 'purchase_order') {
            foreach(array_slice($this->settings->pdf_variables->vendor_details,1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        }
        else{
            foreach(array_slice($this->settings->pdf_variables->client_details,1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        }
        
        return $this->convertVariables($user_details);
    }

    private function getProducts()
    {
        $product_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5;
        })->map(function ($item){
            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $this->entity->client ?: $this->entity->vendor),
                'notes' => $item->notes,
                'line_total' => Number::formatMoney($item->line_total, $this->entity->client ?: $this->entity->vendor),
            ];
        });

        return $product_items;
    }

    private function getServices()
    {
        $task_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        })->map(function ($item){
            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $this->entity->client ?: $this->entity->vendor),
                'notes' => $item->notes,
                'line_total' => Number::formatMoney($item->line_total, $this->entity->client ?: $this->entity->vendor),
            ];
        });

        return $task_items;

    }

    private function resolveEntityType() :string
    {
        if ($this->invitation instanceof InvoiceInvitation) {
            return 'invoice';
        } elseif ($this->invitation instanceof QuoteInvitation) {
            return 'quote';
        } elseif ($this->invitation instanceof CreditInvitation) {
            return 'credit';
        } elseif ($this->invitation instanceof RecurringInvoiceInvitation) {
            return 'recurring_invoice';
        } elseif ($this->invitation instanceof PurchaseOrderInvitation) {
            return 'purchase_order';
        }

        return '';
    }
}

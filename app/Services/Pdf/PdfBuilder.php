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

use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use DOMDocument;
use DOMXPath;

class PdfBuilder
{
    use MakesDates;

    public PdfService $service;

    public array $sections = [];

    public function __construct(PdfService $service)
    {
        $this->service = $service;
    }

    public function build()
    {

        $this->getTemplate()
             ->buildSections();

    }

    private function getTemplate() :self
    {

        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML(mb_convert_encoding($this->service->config->designer->template, 'HTML-ENTITIES', 'UTF-8'));

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    private function getProductSections(): self
    {
        $this->genericSectionBuilder()
             ->getClientDetails()
             ->getProductAndTaskTables()
             ->getProductEntityDetails()
             ->getProductTotals();
    }

    private function getDeliveryNoteSections(): self
    {

        $this->sections[] = [            
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDeliveryDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->deliveryNoteDetails(),
            ],
        ];

        return $this;

    }

    private function getStatementSections(): self
    {

        // 'statement-invoice-table' => [
        //     'id' => 'statement-invoice-table',
        //     'elements' => $this->statementInvoiceTable(),
        // ],
        // 'statement-invoice-table-totals' => [
        //     'id' => 'statement-invoice-table-totals',
        //     'elements' => $this->statementInvoiceTableTotals(),
        // ],
        // 'statement-payment-table' => [
        //     'id' => 'statement-payment-table',
        //     'elements' => $this->statementPaymentTable(),
        // ],
        // 'statement-payment-table-totals' => [
        //     'id' => 'statement-payment-table-totals',
        //     'elements' => $this->statementPaymentTableTotals(),
        // ],
        // 'statement-aging-table' => [
        //     'id' => 'statement-aging-table',
        //     'elements' => $this->statementAgingTable(),
        // ],

    }

    private function getPurchaseOrderSections(): self
    {

        $this->sections[] = [            
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->purchaseOrderDetails(),
            ],
        ];

        return $this;

    }

    private function genericSectionBuilder(): self
    {

        $this->sections[] = [
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->companyAddress(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [
                    $this->sharedFooterElements(),
                ],
            ],
        ];

        return $this;
    }

    private function getProductTotals(): self
    {

        $this->sections[] = [    
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->tableTotals(),
            ],
        ];

        return $this;
    }

    private function getProductEntityDetails(): self
    {


        if($this->service->config->entity_string == 'invoice')
        {
            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->invoiceDetails(),
                ],
            ];
        }
        elseif($this->service->config->entity_string == 'quote')
        {

            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->quoteDetails(),
                ],
            ];

        }
        elseif($this->service->config->entity_string == 'credit')
        {

            $this->sections[] = [
                'entity-details' => [
                    'id' => 'entity-details',
                    'elements' => $this->creditDetails(),
                ],
            ];

        }

        return $this;
        

    }

    private function buildSections() :self
    {

        return  match ($this->service->config->document_type) {
            PdfService::PRODUCT => $this->getProductSections,
            PdfService::DELIVERY_NOTE => $this->getDeliveryNoteSections(),
            PdfService::STATEMENT => $this->getStatementSections(),
            PdfService::PURCHASE_ORDER => $this->getPurchaseOrderSections(),
        };      

    }

    private function statementTableTotals(): array
    {
        return [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: block; align-items: flex-start; page-break-inside: avoid; visible !important;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->service->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
        ];
    }

    //todo, split this down into each entity to make this more readable
    public function getTableTotals() :self
    {
        //need to see where we don't pass all these particular variables. try and refactor thisout
        $_variables = array_key_exists('variables', $this->context)
            ? $this->context['variables']
            : ['values' => ['$entity.public_notes' => $this->service->config->entity->public_notes, '$entity.terms' => $this->service->config->entity->terms, '$entity_footer' => $this->service->config->entity->footer], 'labels' => []];

        $variables = $this->service->config->pdf_variables['total_columns'];

        $elements = [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'p', 'content' => strtr(str_replace(["labels","values"], ["",""], $_variables['values']['$entity.public_notes']), $_variables), 'properties' => ['data-ref' => 'total_table-public_notes', 'style' => 'text-align: left;']],
                ['element' => 'p', 'content' => '', 'properties' => ['style' => 'text-align: left; display: flex; flex-direction: column; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'span', 'content' => '$entity.terms_label: ', 'properties' => ['hidden' => $this->entityVariableCheck('$entity.terms'), 'data-ref' => 'total_table-terms-label', 'style' => 'font-weight: bold; text-align: left; margin-top: 1rem;']],
                    ['element' => 'span', 'content' => strtr(str_replace("labels", "", $_variables['values']['$entity.terms']), $_variables['labels']), 'properties' => ['data-ref' => 'total_table-terms', 'style' => 'text-align: left;']],
                ]],
                ['element' => 'img', 'properties' => ['style' => 'max-width: 50%; height: auto;', 'src' => '$contact.signature', 'id' => 'contact-signature']],
                ['element' => 'div', 'properties' => ['style' => 'margin-top: 1.5rem; display: flex; align-items: flex-start; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem;', 'hidden' => $this->service->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
            ['element' => 'div', 'properties' => ['class' => 'totals-table-right-side', 'dir' => '$dir'], 'elements' => []],
        ];


        if ($this->type == self::DELIVERY_NOTE) {
            return $elements;
        }

        if ($this->entity instanceof Quote) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$outstanding', $variables)) {
                $variables = \array_diff($variables, ['$outstanding']);
            }

            if ($this->service->config->entity->partial > 0) {
                $variables[] = '$partial_due';
            }
        }

        if ($this->entity instanceof Credit) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }

        }

        foreach (['discount'] as $property) {
            $variable = sprintf('%s%s', '$', $property);

            if (
                !is_null($this->service->config->entity->{$property}) &&
                !empty($this->service->config->entity->{$property}) &&
                $this->service->config->entity->{$property} != 0
            ) {
                continue;
            }

            $variables = array_filter($variables, function ($m) use ($variable) {
                return $m != $variable;
            });
        }

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes') {
                $taxes = $this->service->config->entity->calc()->getTotalTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->vendor : $this->client), 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i]],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->service->config->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->vendor : $this->client), 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i]],
                    ]];
                }
            } elseif (Str::startsWith($variable, '$custom_surcharge')) {
                $_variable = ltrim($variable, '$'); // $custom_surcharge1 -> custom_surcharge1

                $visible = intval($this->service->config->entity->{$_variable}) != 0;

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } elseif (Str::startsWith($variable, '$custom')) {
                $field = explode('_', $variable);
                $visible = is_object($this->company->custom_fields) && property_exists($this->company->custom_fields, $field[1]) && !empty($this->company->custom_fields->{$field[1]});

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            }
        }

        $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
            ['element' => 'span', 'content' => '',],
            ['element' => 'span', 'content' => ''],
        ]];

        return $elements;
    

    }


    public function getProductAndTaskTables(): self
    {
        
        $this->sections[] = [
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
            ],
        ];

        return $this;
    }

    public function getClientDetails(): self
    {
        $this->sections[] = [
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
        ];

        return $this;
    }

/**
     * Parent method for building products table.
     *
     * @return array
     */
    public function productTable(): array
    {
        $product_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5;
        });

        if (count($product_items) == 0) {
            return [];
        }

        // if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
        //     return [];
        // }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('product')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$product')],
        ];
    }

    /**
     * Parent method for building tasks table.
     *
     * @return array
     */
    public function taskTable(): array
    {
        $task_items = collect($this->service->config->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        });

        if (count($task_items) == 0) {
            return [];
        }

        // if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
        //     return [];
        // }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('task')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
    }




    public function statementDetails(): array
    {

        $s_date = $this->translateDate(now(), $this->client->date_format(), $this->client->locale());
        
        return [
            ['element' => 'tr', 'properties' => ['data-ref' => 'statement-label'], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ""],
                ['element' => 'th', 'properties' => [], 'content' => "<h2>".ctrans('texts.statement')."</h2>"],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => ctrans('texts.statement_date')],
                ['element' => 'th', 'properties' => [], 'content' => $s_date ?? ''],
            ]],
            ['element' => 'tr', 'properties' => [], 'elements' => [
                ['element' => 'th', 'properties' => [], 'content' => '$balance_due_label'],
                ['element' => 'th', 'properties' => [], 'content' => Number::formatMoney($this->invoices->sum('balance'), $this->client)],
            ]],
        ];

    }

    public function invoiceDetails(): array
    {

       $variables = $this->service->config->pdf_variables['invoice_details'];

        return $this->genericDetailsBuilder($variables);
    }

    public function quoteDetails(): array
    {
        $variables = $this->service->config->pdf_variables['quote_details'];
        
        if ($this->service->config->entity->partial > 0) {
            $variables[] = '$quote.balance_due';
        }

        return $this->genericDetailsBuilder($variables);
    }

    public function creditDetails(): array
    {
    
        $variables = $this->service->config->pdf_variables['credit_details'];
    
        return $this->genericDetailsBuilder($variables);
    }

    public function purchaseOrderDetails(): array
    {

        $variables = $this->service->config->pdf_variables['purchase_order_details'];

        return $this->genericDetailsBuilder($variables);
    
    }

    public function deliveryNoteDetails(): array
    {

        $variables = $this->service->config->pdf_variables['invoice_details'];

        $variables = array_filter($variables, function ($m) {
            return !in_array($m, ['$invoice.balance_due', '$invoice.total']);
        });

        return $this->genericDetailsBuilder($variables);
    }

    public function genericDetailsBuilder(array $variables): array
    {

        $elements = [];


        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            $var = str_replace("custom", "custom_value", $_variable);

            if (in_array($_variable, $_customs) && !empty($this->service->config->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;
    }



    public function clientDeliveryDetails(): array
    {
        
        $elements = [];

        if(!$this->service->config->client)
            return $elements;

        $elements = [
                ['element' => 'p', 'content' => ctrans('texts.delivery_note'), 'properties' => ['data-ref' => 'delivery_note-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
                ['element' => 'p', 'content' => $this->service->config->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.name']],
                ['element' => 'p', 'content' => $this->service->config->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address1']],
                ['element' => 'p', 'content' => $this->service->config->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address2']],
                ['element' => 'p', 'show_empty' => false, 'elements' => [
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_city} ", 'properties' => ['ref' => 'delivery_note-client.shipping_city']],
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_state} ", 'properties' => ['ref' => 'delivery_note-client.shipping_state']],
                    ['element' => 'span', 'content' => "{$this->service->config->client->shipping_postal_code} ", 'properties' => ['ref' => 'delivery_note-client.shipping_postal_code']],
                ]],
                ['element' => 'p', 'content' => optional($this->service->config->client->shipping_country)->name, 'show_empty' => false],
            ];

            if (!is_null($this->service->config->contact)) {
                $elements[] = ['element' => 'p', 'content' => $this->service->config->contact->email, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-contact.email']];
            }

        return $elements;

    }

    public function clientDetails(): array
    {
        $elements = [];

        if(!$this->service->config->client)
            return $elements;

        $variables = $this->service->config->pdf_variables['client_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    //todo
    public function deliveryNoteTable(): array
    {
    
        $thead = [
            ['element' => 'th', 'content' => '$item_label', 'properties' => ['data-ref' => 'delivery_note-item_label']],
            ['element' => 'th', 'content' => '$description_label', 'properties' => ['data-ref' => 'delivery_note-description_label']],
            ['element' => 'th', 'content' => '$product.quantity_label', 'properties' => ['data-ref' => 'delivery_note-product.quantity_label']],
        ];

        $items = $this->transformLineItems($this->service->config->entity->line_items, $this->type);

        $this->processNewLines($items);
        $product_customs = [false, false, false, false];

        foreach ($items as $row) {
            for ($i = 0; $i < count($product_customs); $i++) {
                if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                    $product_customs[$i] = true;
                }
            }
        }

        for ($i = 0; $i < count($product_customs); $i++) {
            if ($product_customs[$i]) {
                array_push($thead, ['element' => 'th', 'content' => '$product.product' . ($i + 1) . '_label', 'properties' => ['data-ref' => 'delivery_note-product.product' . ($i + 1) . '_label']]);
            }
        }

        return [
            ['element' => 'thead', 'elements' => $thead],
            ['element' => 'tbody', 'elements' => $this->buildTableBody(self::DELIVERY_NOTE)],
        ];
    }


    public function companyDetails(): array
    {
        $variables = $this->service->config->pdf_variables['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->service->config->pdf_variables['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function vendorDetails(): array
    {
        $elements = [];

        $variables = $this->service->config->pdf_variables['vendor_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
        }

        return $elements;
    }






 //         if (isset($this->data['template']) && isset($this->data['variables'])) {
 //            $this->getEmptyElements($this->data['template'], $this->data['variables']);
 //        }

 //        if (isset($this->data['template'])) {
 //            $this->updateElementProperties($this->data['template']);
 //        }

 //        if (isset($this->data['variables'])) {
 //            $this->updateVariables($this->data['variables']);
 //        }

 //        return $this;



    
}
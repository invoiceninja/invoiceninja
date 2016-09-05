<?php namespace App\Models\Traits;

/**
 * Class PresentsInvoice
 */
trait PresentsInvoice
{
    public function getInvoiceFields()
    {
        if ($this->invoice_fields) {
            $fields = json_decode($this->invoice_fields, true);
        } else {
            $fields = [
                INVOICE_FIELDS_INVOICE => [
                    'invoice_number',
                    'po_number',
                    'invoice_date',
                    'due_date',
                    'balance_due',
                    'partial_due',
                ],
                INVOICE_FIELDS_CLIENT => [
                    'client_name',
                    'id_number',
                    'vat_number',
                    'address1',
                    'address2',
                    'city_state_postal',
                    'country',
                    'email',
                ],
                'company_fields1' => [
                    'company_name',
                    'id_number',
                    'vat_number',
                    'website',
                    'email',
                    'phone',

                ],
                'company_fields2' => [
                    'address1',
                    'address2',
                    'city_state_postal',
                    'country',
                ],
            ];

            if ($this->custom_invoice_text_label1) {
                $fields[INVOICE_FIELDS_INVOICE][] = 'custom_invoice_text_label1';
            }
            if ($this->custom_invoice_text_label2) {
                $fields[INVOICE_FIELDS_INVOICE][] = 'custom_invoice_text_label2';
            }
            if ($this->custom_client_label1) {
                $fields[INVOICE_FIELDS_CLIENT][] = 'custom_client_label1';
            }
            if ($this->custom_client_label2) {
                $fields[INVOICE_FIELDS_CLIENT][] = 'custom_client_label2';
            }
            if ($this->custom_label1) {
                $fields['company_fields2'][] = 'custom_label1';
            }
            if ($this->custom_label2) {
                $fields['company_fields2'][] = 'custom_label2';
            }
        }
        
        return $this->applyLabels($fields);
    }

    public function getAllInvoiceFields()
    {
        $fields = [
            INVOICE_FIELDS_INVOICE => [
                'invoice_number',
                'po_number',
                'invoice_date',
                'due_date',
                'balance_due',
                'partial_due',
                'custom_invoice_text_label1',
                'custom_invoice_text_label2',
            ],
            INVOICE_FIELDS_CLIENT => [
                'client_name',
                'id_number',
                'vat_number',
                'address1',
                'address2',
                'city_state_postal',
                'country',
                'email',
                'contact_name',
                'custom_client_label1',
                'custom_client_label2',
            ],
            INVOICE_FIELDS_COMPANY => [
                'company_name',
                'id_number',
                'vat_number',
                'website',
                'email',
                'phone',
                'address1',
                'address2',
                'city_state_postal',
                'country',
                'custom_label1',
                'custom_label2',
            ]
        ];

        return $this->applyLabels($fields);
    }

    private function applyLabels($fields)
    {
        $labels = $this->getInvoiceLabels();

        foreach ($fields as $section => $sectionFields) {
            foreach ($sectionFields as $index => $field) {
                $fields[$section][$field] = $labels[$field];
                unset($fields[$section][$index]);
            }
        }

        return $fields;

    }

    /**
     * @return array
     */
    public function getInvoiceLabels()
    {
        $data = [];
        $custom = (array) json_decode($this->invoice_labels);

        $fields = [
            'invoice',
            'invoice_date',
            'due_date',
            'invoice_number',
            'po_number',
            'discount',
            'taxes',
            'tax',
            'item',
            'description',
            'unit_cost',
            'quantity',
            'line_total',
            'subtotal',
            'paid_to_date',
            'balance_due',
            'partial_due',
            'terms',
            'your_invoice',
            'quote',
            'your_quote',
            'quote_date',
            'quote_number',
            'total',
            'invoice_issued_to',
            'quote_issued_to',
            'rate',
            'hours',
            'balance',
            'from',
            'to',
            'invoice_to',
            'details',
            'invoice_no',
            'valid_until',
            'client_name',
            'address1',
            'address2',
            'id_number',
            'vat_number',
            'city_state_postal',
            'country',
            'email',
            'contact_name',
            'company_name',
            'website',
            'phone',
        ];

        foreach ($fields as $field) {
            if (isset($custom[$field]) && $custom[$field]) {
                $data[$field] = $custom[$field];
            } else {
                $data[$field] = $this->isEnglish() ? uctrans("texts.$field") : trans("texts.$field");
            }
        }

        foreach (['item', 'quantity', 'unit_cost'] as $field) {
            $data["{$field}_orig"] = $data[$field];
        }

        foreach ([
            'custom_label1',
            'custom_label2',
            'custom_client_label1',
            'custom_client_label2',
            'custom_invoice_text_label1',
            'custom_invoice_text_label2',
        ] as $field) {
            $data[$field] = $this->$field ?: trans('texts.custom_field');
        }

        return $data;
    }


}

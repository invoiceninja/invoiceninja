<?php

namespace App\Models\Traits;

/**
 * Class PresentsInvoice.
 */
trait PresentsInvoice
{
    public function getInvoiceFields()
    {
        if ($this->invoice_fields) {
            $fields = json_decode($this->invoice_fields, true);

            return $this->applyLabels($fields);
        } else {
            return $this->getDefaultInvoiceFields();
        }
    }

    public function getDefaultInvoiceFields()
    {
        $fields = [
            INVOICE_FIELDS_INVOICE => [
                'invoice.invoice_number',
                'invoice.po_number',
                'invoice.invoice_date',
                'invoice.due_date',
                'invoice.balance_due',
                'invoice.partial_due',
            ],
            INVOICE_FIELDS_CLIENT => [
                'client.client_name',
                'client.id_number',
                'client.vat_number',
                'client.address1',
                'client.address2',
                'client.city_state_postal',
                'client.country',
                'client.email',
            ],
            'account_fields1' => [
                'account.company_name',
                'account.id_number',
                'account.vat_number',
                'account.website',
                'account.email',
                'account.phone',

            ],
            'account_fields2' => [
                'account.address1',
                'account.address2',
                'account.city_state_postal',
                'account.country',
            ],
        ];

        if ($this->custom_invoice_text_label1) {
            $fields[INVOICE_FIELDS_INVOICE][] = 'invoice.custom_text_value1';
        }
        if ($this->custom_invoice_text_label2) {
            $fields[INVOICE_FIELDS_INVOICE][] = 'invoice.custom_text_value2';
        }
        if ($this->custom_client_label1) {
            $fields[INVOICE_FIELDS_CLIENT][] = 'client.custom_value1';
        }
        if ($this->custom_client_label2) {
            $fields[INVOICE_FIELDS_CLIENT][] = 'client.custom_value2';
        }
        if ($this->custom_contact_label1) {
            $fields[INVOICE_FIELDS_CLIENT][] = 'contact.custom_value1';
        }
        if ($this->custom_contact_label2) {
            $fields[INVOICE_FIELDS_CLIENT][] = 'contact.custom_value2';
        }
        if ($this->custom_label1) {
            $fields['account_fields2'][] = 'account.custom_value1';
        }
        if ($this->custom_label2) {
            $fields['account_fields2'][] = 'account.custom_value2';
        }

        return $this->applyLabels($fields);
    }

    public function getAllInvoiceFields()
    {
        $fields = [
            INVOICE_FIELDS_INVOICE => [
                'invoice.invoice_number',
                'invoice.po_number',
                'invoice.invoice_date',
                'invoice.due_date',
                'invoice.invoice_total',
                'invoice.balance_due',
                'invoice.partial_due',
                'invoice.outstanding',
                'invoice.custom_text_value1',
                'invoice.custom_text_value2',
                '.blank',
            ],
            INVOICE_FIELDS_CLIENT => [
                'client.client_name',
                'client.id_number',
                'client.vat_number',
                'client.website',
                'client.work_phone',
                'client.address1',
                'client.address2',
                'client.city_state_postal',
                'client.postal_city_state',
                'client.country',
                'client.contact_name',
                'client.email',
                'client.phone',
                'client.custom_value1',
                'client.custom_value2',
                'contact.custom_value1',
                'contact.custom_value2',
                '.blank',
            ],
            INVOICE_FIELDS_ACCOUNT => [
                'account.company_name',
                'account.id_number',
                'account.vat_number',
                'account.website',
                'account.email',
                'account.phone',
                'account.address1',
                'account.address2',
                'account.city_state_postal',
                'account.postal_city_state',
                'account.country',
                'account.custom_value1',
                'account.custom_value2',
                '.blank',
            ],
        ];

        return $this->applyLabels($fields);
    }

    private function applyLabels($fields)
    {
        $labels = $this->getInvoiceLabels();

        foreach ($fields as $section => $sectionFields) {
            foreach ($sectionFields as $index => $field) {
                list($entityType, $fieldName) = explode('.', $field);
                if (substr($fieldName, 0, 6) == 'custom') {
                    $fields[$section][$field] = $labels[$field];
                } elseif (in_array($field, ['client.phone', 'client.email'])) {
                    $fields[$section][$field] = trans('texts.contact_' . $fieldName);
                } else {
                    $fields[$section][$field] = $labels[$fieldName];
                }
                unset($fields[$section][$index]);
            }
        }

        return $fields;
    }

    public function hasCustomLabel($field)
    {
        $custom = (array) json_decode($this->invoice_labels);

        return isset($custom[$field]) && $custom[$field];
    }

    public function getLabel($field, $override = false)
    {
        $custom = (array) json_decode($this->invoice_labels);

        if (isset($custom[$field]) && $custom[$field]) {
            return $custom[$field];
        } else {
            if ($override) {
                $field = $override;
            }
            return $this->isEnglish() ? uctrans("texts.$field") : trans("texts.$field");
        }

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
            'quote_to',
            'details',
            'invoice_no',
            'quote_no',
            'valid_until',
            'client_name',
            'address1',
            'address2',
            'id_number',
            'vat_number',
            'city_state_postal',
            'postal_city_state',
            'country',
            'email',
            'contact_name',
            'company_name',
            'website',
            'phone',
            'blank',
            'surcharge',
            'tax_invoice',
            'tax_quote',
            'statement',
            'statement_date',
            'your_statement',
            'statement_issued_to',
            'statement_to',
            'credit_note',
            'credit_date',
            'credit_number',
            'credit_issued_to',
            'credit_to',
            'your_credit',
            'work_phone',
            'invoice_total',
            'outstanding',
            'invoice_due_date',
            'quote_due_date',
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
            'account.custom_value1' => 'custom_label1',
            'account.custom_value2' => 'custom_label2',
            'invoice.custom_text_value1' => 'custom_invoice_text_label1',
            'invoice.custom_text_value2' => 'custom_invoice_text_label2',
            'client.custom_value1' => 'custom_client_label1',
            'client.custom_value2' => 'custom_client_label2',
            'contact.custom_value1' => 'custom_contact_label1',
            'contact.custom_value2' => 'custom_contact_label2',
        ] as $field => $property) {
            $data[$field] = $this->$property ?: trans('texts.custom_field');
        }

        return $data;
    }

    public function getCustomDesign($designId) {
        if ($designId == CUSTOM_DESIGN1) {
            return $this->custom_design1;
        } elseif ($designId == CUSTOM_DESIGN2) {
            return $this->custom_design2;
        } elseif ($designId == CUSTOM_DESIGN3) {
            return $this->custom_design3;
        }

        return null;
    }
}

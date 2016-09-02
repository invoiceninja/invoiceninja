<?php namespace App\Models\Traits;

/**
 * Class PresentsInvoice
 */
trait PresentsInvoice
{
    public function getInvoiceFields()
    {
        $labels = $this->getInvoiceLabels();

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

            ],
            INVOICE_FIELDS_ACCOUNT => [

            ]
        ];

        foreach ($fields as $section => $sectionFields) {
            foreach ($sectionFields as $index => $field) {
                $fields[$section][$field] = $labels[$field];
                unset($fields[$section][$index]);
            }
        }

        if ($this->custom_invoice_text_label1) {
            //$fields[INVOICE_FIELDS_INVOICE][] = ''
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
            //'date',
            'rate',
            'hours',
            'balance',
            'from',
            'to',
            'invoice_to',
            'details',
            'invoice_no',
            'valid_until',
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

        return $data;
    }


}

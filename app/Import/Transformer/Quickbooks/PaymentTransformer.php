<?php

/**
 * Invoice Ninja (https://Paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Quickbooks;

use App\Import\Transformer\Quickbooks\CommonTrait;
use App\Import\Transformer\BaseTransformer;
use App\Models\Payment as Model;
use App\Import\ImportException;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\Invoice;

/**
 *
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    use CommonTrait;

    protected $fillable = [
        'number' => "PaymentRefNum",
        'amount' => "TotalAmt",
        "client_id" => "CustomerRef",
        "currency_id" => "CurrencyRef",
        'date' => "TxnDate",
        "invoices" => "Line",
        'private_notes' => "PrivateNote",
        'created_at' => "CreateTime",
        'updated_at' => "LastUpdatedTime"
    ];

    public function __construct($company)
    {
        parent::__construct($company);

        $this->model = new Model();
    }

    public function getTotalAmt($data, $field = null)
    {
        return (float) $this->getString($data, $field);
    }

    public function getTxnDate($data, $field = null)
    {
        return $this->parseDateOrNull($data, $field);
    }

    public function getCustomerRef($data, $field = null)
    {
        return $this->getClient($this->getString($data, 'CustomerRef.name'), null);
    }

    public function getCurrencyRef($data, $field = null)
    {
        return $this->getCurrencyByCode($data['CurrencyRef'], 'value');
    }

    public function getLine($data, $field = null)
    {
        $invoices = [];
        $invoice = $this->getString($data, 'Line.LinkedTxn.TxnType');
        if(is_null($invoice) || $invoice !== 'Invoice') {
            return $invoices;
        }
        if(is_null(($invoice_id = $this->getInvoiceId($this->getString($data, 'Line.LinkedTxn.TxnId.value'))))) {
            return $invoices;
        }

        return [[
            'amount' => (float) $this->getString($data, 'Line.Amount'),
            'invoice_id' => $invoice_id
        ]];
    }

    /**
    * @param $invoice_number
    *
    * @return int|null
    */
    public function getInvoiceId($invoice_number)
    {
        $invoice = Invoice::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->where(
                "number",
                "LIKE",
                "%-$invoice_number%",
            )
            ->first();

        return $invoice ? $invoice->id : null;
    }

}

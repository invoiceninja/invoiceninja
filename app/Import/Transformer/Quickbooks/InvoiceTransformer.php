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

namespace App\Import\Transformer\Quickbooks;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Import\ImportException;
use App\DataMapper\InvoiceItem;
use App\Models\Invoice as Model;
use App\Import\Transformer\BaseTransformer;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    

    private $fillable = [
        'amount' => "TotalAmt",
        'line_items' => "Line",
        'due_date' => "DueDate",
        'partial' => "Deposit",
        'balance' => "Balance",
        'comments' => "CustomerMemo",
        'number' => "DocNumber",
        'created_at' => "CreateTime",
        'updated_at' => "LastUpdatedTime"
    ];

    public function transform($data)
    {
        $transformed = [];

        foreach ($this->fillable as $key => $field) {
            $transformed[$key] = is_null((($v = $this->getString($data, $field))))? null : (method_exists($this, ($method = "get{$field}")) ? call_user_func([$this, $method], $data, $field ) : $this->getString($data,$field));
        }

        return (new Model)->fillable(array_keys($this->fillable))->fill($transformed)->toArray();
    }

    public function getTotalAmt($data)
    {
        return (float) $this->getString($data,'TotalAmt');
    }

    public function getLine($data)
    {
        return array_map(function ($item) {
            return [
                'description' => $this->getString($item,'Description'),
                'quantity' => $this->getString($item,'SalesItemLineDetail.Qty'),
                'unit_price' =>$this->getString($item,'SalesItemLineDetail.UnitPrice'),
                'amount' => $this->getString($item,'Amount')
            ];
        }, array_filter($this->getString($data,'Line'), function ($item) {
            return $this->getString($item,'DetailType') === 'SalesItemLineDetail';
        }));
    }

    public function getString($data,$field) {
        return Arr::get($data,$field);
    }

    public function getDueDate($data)
    {
        return $this->parseDateOrNull($data, 'DueDate');
    }

    public function getDeposit($data)
    {
        return (float) $this->getString($data,'Deposit');
    }

    public function getBalance($data)
    {
        return (float) $this->getString($data,'Balance');
    }

    public function getCustomerMemo($data)
    {
        return $this->getString($data,'CustomerMemo.value');
    }

    public function getCreateTime($data)
    {
        return $this->parseDateOrNull($data['MetaData'], 'CreateTime');
    }

    public function getLastUpdatedTime($data)
    {
        return $this->parseDateOrNull($data['MetaData'],'LastUpdatedTime');
    }
}

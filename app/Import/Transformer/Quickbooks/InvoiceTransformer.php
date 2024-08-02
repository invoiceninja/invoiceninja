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
use App\Import\Transformer\Quickbooks\ClientTransformer;

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

        return (new Model)->fillable(array_keys($this->fillable))->fill($transformed)->toArray() + $this->getInvoiceClient($data);
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
            return $this->getString($item,'DetailType') !== 'SubTotalLineDetail';
        }));
    }

    public function getInvoiceClient($data, $field = null) {
        /**
         *  "CustomerRef": {
                "value": "23",
                "name": ""Barnett Design
                },
                "CustomerMemo": {
                "value": "Thank you for your business and have a great day!"
                },
                "BillAddr": {
                "Id": "58",
                "Line1": "Shara Barnett",
                "Line2": "Barnett Design",
                "Line3": "19 Main St.",
                "Line4": "Middlefield, CA  94303",
                "Lat": "37.4530553",
                "Long": "-122.1178261"
                },
                "ShipAddr": {
                "Id": "24",
                "Line1": "19 Main St.",
                "City": "Middlefield",
                "CountrySubDivisionCode": "CA",
                "PostalCode": "94303",
                "Lat": "37.445013",
                "Long": "-122.1391443"
                },"BillEmail": {
                "Address": "Design@intuit.com"
                },
                    [
                    'name'              => 'CompanyName',
                    'phone'             => 'PrimaryPhone.FreeFormNumber',
                    'country_id'        => 'BillAddr.Country',
                    'state'             => 'BillAddr.CountrySubDivisionCode',
                    'address1'          => 'BillAddr.Line1',
                    'city'              => 'BillAddr.City',
                    'postal_code'       => 'BillAddr.PostalCode',
                    'shipping_country_id' => 'ShipAddr.Country',
                    'shipping_state'    => 'ShipAddr.CountrySubDivisionCode',
                    'shipping_address1' => 'ShipAddr.Line1',
                    'shipping_city'     => 'ShipAddr.City',
                    'shipping_postal_code' => 'ShipAddr.PostalCode',
                    'public_notes'      => 'Notes'
                ];

         */
        $bill_address = (object) $this->getString($data, 'BillAddr');
        $ship_address = $this->getString($data, 'ShipAddr');
        $customer = explode(" ", $this->getString($data, 'CustomerRef.name'));
        $customer = ['GivenName' => $customer[0], 'FamilyName' => $customer[1]];
        $has_company = property_exists($bill_address, 'Line4');
        $address = $has_company?  $bill_address->Line4 : $bill_address->Line3;
        $address_1 = substr($address, 0, stripos($address,','));
        $address =array_filter( [$address_1] + (explode(' ', substr($address, stripos($address,",") + 1 ))));
        $client = 
        [
            "CompanyName" => $has_company?  $bill_address->Line2 : $bill_address->Line1,
            "BillAddr" => array_combine(['City','CountrySubDivisionCode','PostalCode'], $address) + ['Line1' => $has_company? $bill_address->Line3 : $bill_address->Line2 ],
            "ShipAddr" => $ship_address
        ] + $customer + ['PrimaryEmailAddr' => ['Address' => $this->getString($data, 'BillEmail.Address') ]];
        $client_id = $this->getClient($client['CompanyName'],$this->getString($client, 'PrimaryEmailAddr.Address'));
       
        return ['client'=> (new ClientTransformer($this->company))->transform($client), 'client_id'=> $client_id ];
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

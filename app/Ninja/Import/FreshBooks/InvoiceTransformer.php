<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:47
 */

namespace app\Ninja\Import\FreshBooks;

use App\Models\Client;
use Exception;
use League\Fractal\Resource\Collection;
use League\Fractal\TransformerAbstract;
use stdClass;

class InvoiceTransformer extends TransformerAbstract
{



    public function transform($data)
    {
        return new Collection($data, function(array $data) {
            $data = $this->arrayToObject($data);
            $client = Client::where('name', $data->organization)->orderBy('created_at', 'desc')->first();
            $data->client_id = $client->id;
            $data->user_id = $client->user_id;
            $data->account_id = $client->account_id;
            $create_date = new \DateTime($data->create_date);
            $data->create_date = date_format($create_date, DEFAULT_DATE_FORMAT);
            return [
                'invoice_number'   => $data->invoice_number     !== array() ? $data->invoice_number : '',
                'client_id'        => (int)$data->client_id     !== array() ? $data->client_id      : '',
                'user_id'          => (int)$data->user_id       !== array() ? $data->user_id        : '',
                'account_id'       => (int)$data->account_id    !== array() ? $data->account_id     : '',
                'amount'           => (int)$data->amount        !== array() ? $data->amount         : '',
                'po_number'        => $data->po_number          !== array() ? $data->po_number      : '',
                'terms'            => $data->terms              !== array() ? $data->terms          : '',
                'public_notes'     => $data->notes              !== array() ? $data->notes          : '',
                //Best guess on required fields
                'invoice_date'     => $data->create_date        !== array() ? $data->create_date    : '',
                'due_date'     => $data->create_date        !== array() ? $data->create_date    : '',
                'discount'         => 0,
                'invoice_footer'         => '',
                'invoice_design_id'         => 1,
                'invoice_items'         => '',
                'is_amount_discount'    => 0,
                'partial'          => 0,
                'invoice_items' => [
                    [
                        'product_key'       => '',
                        'notes'             => $data->notes              !== array() ? $data->notes          : '',
                        'task_public_id'    => '',
                        'cost'              => (int)$data->amount        !== array() ? $data->amount         : '',
                        'qty'               => 1,
                        'tax'               => '',
                        'tax_name'          => '',
                        'tax_rate'          => 0
                    ]
                ],

            ];
        });
    }

    private function arrayToObject($array)
    {
        $object                     = new stdClass();
        $object->invoice_number     = $array[0];
        $object->organization       = $array[1];
        $object->fname              = $array[2];
        $object->lname              = $array[3];
        $object->amount             = $array[4];
        $object->paid               = $array[5];
        $object->po_number          = $array[6];
        $object->create_date        = $array[7];
        $object->date_paid          = $array[8];
        $object->terms              = $array[9];
        $object->notes              = $array[10];
        return $object;
    }

    public function validateHeader($csvHeader)
    {
        $header = [0 => "invoice_number",
            1 => "organization",
            2 => "fname",
            3 => "lname",
            4 => "amount",
            5 => "paid",
            6 => "po_number",
            7 => "create_date",
            8 => "date_paid",
            9 => "terms",
            10 => "notes"];

        if(!empty(array_diff($header, $csvHeader)))
            throw new Exception(trans('texts.invalid_csv_header'));
    }


}
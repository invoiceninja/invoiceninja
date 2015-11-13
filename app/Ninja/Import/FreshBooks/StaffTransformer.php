<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:47
 */

namespace app\Ninja\Import\FreshBooks;

use League\Fractal\TransformerAbstract;
use League\Fractal\Resource\Collection;
use stdClass;
use Illuminate\Support\Facades\Auth;
use Exception;

class StaffTransformer extends TransformerAbstract
{
    public function transform($data)
    {
        return new Collection($data, function(array $data) {
            $data = $this->arrayToObject($data);
            return [
                'account_id'    => Auth::user()->account_id,
                'first_name'    => $data->fname     !== array() ? $data->fname          : '',
                'last_name'     => $data->lname     !== array() ? $data->lname          : '',
                'phone'         => $data->bus_phone !== array() ? $data->bus_phone      : $data->mob_phone,
                'username'      => $data->email     !== array() ? $data->email          : '',
                'email'         => $data->email     !== array() ? $data->email          : '',
            ];
        });
    }

    private function arrayToObject($array)
    {
        $object                 = new stdClass();
        $object->fname      = $array[0];
        $object->lname      = $array[1];
        $object->email      = $array[2];
        $object->p_stret    = $array[3];
        $object->p_street2  = $array[4];
        $object->p_city     = $array[5];
        $object->p_province = $array[6];
        $object->p_country  = $array[7];
        $object->p_code     = $array[8];
        $object->bus_phone  = $array[9];
        $object->home_phone = $array[10];
        $object->mob_phone  = $array[11];
        $object->fax        = $array[12];
        $object->s_street   = $array[13];
        $object->s_street2  = $array[14];
        $object->s_city     = $array[15];
        $object->s_province = $array[16];
        $object->s_country  = $array[17];
        $object->s_code     = $array[18];
        return $object;
    }

    public function validateHeader($csvHeader)
    {
        $header = [0 => "fname",
            1 => "lname",
            2 => "email",
            3 => "p_stret",
            4 => "p_street2",
            5 => "p_city",
            6 => "p_province",
            7 => "p_country",
            8 => "p_code",
            9 => "bus_phone",
            10 => "home_phone",
            11 => "mob_phone",
            12 => "fax",
            13 => "s_street",
            14 => "s_street2",
            15 => "s_city",
            16 => "s_province",
            17 => "s_country",
            18 => "s_code"];

        if(empty($difference))
            return;

        $difference = array_diff($header, $csvHeader);
        $difference = implode(',', $difference);
        throw new Exception(trans('texts.invalid_csv_header') . " - $difference - ");
    }
}
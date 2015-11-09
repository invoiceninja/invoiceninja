<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/8/15
 * Time: 10:38
 */

namespace app\Ninja\Import\FreshBooks;

use App\Ninja\Import\FreshBooks\MapperInterface;
use App\Ninja\Repositories\ClientRepository;
use League\Fractal\Resource\Collection;
use Exception;
use App\Models\Country;
use stdClass;

class ClientMapper implements MapperInterface
{
    public function __construct(ClientRepository $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    public function validateHeader($csvHeader)
    {
        $clientCSVHeader = [0 => "Organization",
            1 => "FirstName",
            2 => "LastName",
            3 => "Email",
            4 => "Street",
            5 => "Street2",
            6 => "City",
            7 => "Province",
            8 => "Country",
            9 => "PostalCode",
            10 => "BusPhone",
            11 => "HomePhone",
            12 => "MobPhone",
            13 => "Fax",
            14 => "SecStreet",
            15 => "SecStreet2",
            16 => "SecCity",
            17 => "SecProvince",
            18 => "SecCountry",
            19 => "SecPostalCode",
            20 => "Notes"];

        if(!empty(array_diff($clientCSVHeader, $clientCSVHeader)))
            throw new Exception(trans('texts.invalid_csv_header'));
    }

    public function getResourceMapper($data)
    {
        return new Collection($data, function(array $data) {

            $data = $this->arrayToObject($data);
            return [
                'name'          => $data->organization          !== array() ? $data->organization   : '',
                'work_phone'    => $data->busPhone              !== array() ? $data->busPhone       : '',
                'address1'      => $data->street                !== array() ? $data->street         : '',
                'address2'      => $data->street2               !== array() ? $data->street2        : '',
                'city'          => $data->city                  !== array() ? $data->city           : '',
                'state'         => $data->province              !== array() ? $data->province       : '',
                'postal_code'   => $data->postalCode            !== array() ? $data->postalCode     : '',
                'private_notes'    => $data->notes              !== array() ? $data->notes          : '',
                'contacts'  => [
                    [
                        'public_id'     => '',
                        'first_name'    => $data->firstName     !== array() ? $data->firstName      : '',
                        'last_name'     => $data->lastName      !== array() ? $data->lastName       : '',
                        'email'         => $data->email         !== array() ? $data->email          : '',
                        'phone'         => $data->mobPhone      !== array() ? $data->mobPhone : $data->homePhone,
                    ]
                ],
                'country_id'    => !Country::where('name',$data->country)->get()->isEmpty()?Country::where('name',$data->country)->first()->id:null,
            ];
        });
    }

    public function save($row)
    {
        $this->clientRepository->save($row);
    }

    private function arrayToObject($array)
    {
        $object                 = new stdClass();
        $object->organization   = $array[0];
        $object->firstName      = $array[1];
        $object->lastName       = $array[2];
        $object->email          = $array[3];
        $object->street         = $array[4];
        $object->street2        = $array[5];
        $object->city           = $array[6];
        $object->province       = $array[7];
        $object->country        = $array[8];
        $object->postalCode     = $array[9];
        $object->busPhone       = $array[10];
        $object->homePhone      = $array[11];
        $object->mobPhone       = $array[12];
        $object->fax            = $array[13];
        $object->secStreet      = $array[14];
        $object->secStreet2     = $array[15];
        $object->secCity        = $array[16];
        $object->secProvince    = $array[17];
        $object->secCountry     = $array[18];
        $object->secPostalCode  = $array[19];
        $object->notes          = $array[20];
        return $object;
    }

}
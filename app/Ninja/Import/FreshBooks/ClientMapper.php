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
            return [
                'name'          => $data[0]                !== array() ? $data[0] : '',
                'work_phone'    => $data[10]               !== array() ? $data[10] : '',
                'address1'      => $data[4]                !== array() ? $data[4] : '',
                'address2'      => $data[5]                !== array() ? $data[5] : '',
                'city'          => $data[6]                !== array() ? $data[6] : '',
                'state'         => $data[7]                !== array() ? $data[7] : '',
                'postal_code'   => $data[9]                !== array() ? $data[9] : '',
                'country_id'    => !Country::where('name',$data[8])->get()->isEmpty()?Country::where('name',$data[8])->first()->id:null,
                'private_notes'    => $data[20]                !== array() ? $data[20] : '',
                'contacts'  => [
                    [
                        'public_id'     => '',
                        'first_name'    => $data[1]        !== array() ? $data[1] : '',
                        'last_name'     => $data[2]        !== array() ? $data[2] : '',
                        'email'         => $data[3]        !== array() ? $data[3] : '',
                        'phone'         => $data[12]       !== array() ? $data[12] : $data[11],
                    ]
                ]
            ];
        });
    }

    public function save($row)
    {
        $this->clientRepository->save($row);
    }

}
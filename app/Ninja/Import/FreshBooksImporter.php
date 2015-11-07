<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/6/15
 * Time: 10:36
 */

namespace app\Ninja\Import;

use App\Models\Client;
use App\Models\Country;
use App\Ninja\Interfaces\ImporterInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Manager;
use Exception;
use Illuminate\Support\Facades\Auth;
use parseCSV;



class FreshBooksImporter implements ImporterInterface
{
    protected $fractal;

    public function __construct(Manager $manager)
    {
        $this->fractal = $manager;
    }

    public function import($file)
    {
        $data = $this->importClientFromCSV($file);
        $ignore_header = true;
        return $this->transformClient($data, $ignore_header);
    }

    public function importClientFromCSV($file)
    {
        if ($file == null)
            throw new Exception(trans('texts.select_file'));

        $name = $file->getRealPath();

        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($name);

        if (count($csv->data) + Client::scope()->count() > Auth::user()->getMaxNumClients()) {
            $message = trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]);
            throw new Exception($message);
        }

        return $csv->data;
    }

    /**
     * @param $data
     *  Header of the Freshbook CSV File
        0 => "Organization"
        1 => "FirstName"
        2 => "LastName"
        3 => "Email"
        4 => "Street"
        5 => "Street2"
        6 => "City"
        7 => "Province"
        8 => "Country"
        9 => "PostalCode"
        10 => "BusPhone"
        11 => "HomePhone"
        12 => "MobPhone"
        13 => "Fax"
        14 => "SecStreet"
        15 => "SecStreet2"
        16 => "SecCity"
        17 => "SecProvince"
        18 => "SecCountry"
        19 => "SecPostalCode"
        20 => "Notes"
     * @param $ignore_header
     * @return mixed
     */
    public function transformClient($data, $ignore_header)
    {
        if($ignore_header)
            $header = array_shift($data);

        $resource = new Collection($data, function(array $data) {
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
        $data = $this->fractal->createData($resource)->toArray();

        return $data['data'];
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/6/15
 * Time: 10:36
 */

namespace app\Ninja\Import;

use App\Models\Client;
use League\Fractal\Resource\Collection;
use League\Fractal\Manager;
use Exception;
use Illuminate\Support\Facades\Auth;
use parseCSV;



class FreshBooks
{
    protected $fractal;

    public function __construct(Manager $manager)
    {
        $this->fractal = $manager;
    }

    public function importCSV($file)
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

    public function transformClient($data)
    {
        $header = array_shift($data);
        $resource = new Collection($data, function(array $data) {
            return [
                'name'          => $data[0]                 !== array() ? $data[0] : '',
                'work_phone'    => $data[10]               !== array() ? $data[10] : '',
                'address1'      => $data[4]                !== array() ? $data[4] : '',
                'address2'      => $data[5]                !== array() ? $data[5] : '',
                'city'          => $data[6]                !== array() ? $data[6] : '',
                'state'         => $data[7]                !== array() ? $data[7] : '',
                'postal_code'   => $data[9]                !== array() ? $data[9] : '',
                'country_id'    => 0,
                'contacts'  => [
                    [
                        'public_id'     => '',
                        'first_name'    => $data[1]        !== array() ? $data[1] : '',
                        'last_name'     => $data[2]        !== array() ? $data[2] : '',
                        'email'         => $data[3]        !== array() ? $data[3] : '',
                        'phone'         => $data[11]       !== array() ? $data[11] : '',
                    ]
                ]
            ];
        });
        $data = $this->fractal->createData($resource)->toArray();

        dd($data);
        return $data;

    }

}
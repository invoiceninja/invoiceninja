<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/7/15
 * Time: 12:05
 */

namespace app\Ninja\Import\FreshBooks;

use League\Fractal\Manager;
use parseCSV;
use Exception;
use app\Ninja\Import\FreshBooks\ClientMapper;

class Importer
{

    protected $entity;
    protected $file;
    protected $mapper;
    protected $repository;
    protected $clientMapper;

    public function __construct(Manager $manager, ClientMapper $clientMapper)
    {
        $this->fractal = $manager;
        $this->clientMapper = $clientMapper;
    }


    public function execute($entity, $file)
    {
        $this->entity = $entity;
        $this->file = $file;
        $this->mapper = $this->getEntityMapper();
        $data = $this->parseCSV($file);
        $ignore_header = true;
        $rows = $this->mapCsvToModel($data, $ignore_header);
        foreach($rows as $row)
            $this->mapper->save($row);

        return $this->file->getClientOriginalName();
    }

    private function parseCSV($file)
    {
        if ($file == null)
            throw new Exception(trans('texts.select_file'));

        $name = $file->getRealPath();

        require_once app_path().'/Includes/parsecsv.lib.php';
        $csv = new parseCSV();
        $csv->heading = false;
        $csv->auto($name);

        //Review this code later. Free users can only have 100 clients.
        /*
        if (count($csv->data) + Client::scope()->count() > Auth::user()->getMaxNumClients()) {
            $message = trans('texts.limit_clients', ['count' => Auth::user()->getMaxNumClients()]);
        }
        */

        return $csv->data;
    }

    /**
     * @param $data
     *  Header of the Freshbook CSV File

     * @param $ignore_header
     * @return mixed
     */
    private function mapCsvToModel($data, $ignore_header)
    {
        if($ignore_header)
        {
            $header = array_shift($data);
            $this->mapper->validateHeader($header);
        }

        $resource = $this->mapper->getResourceMapper($data);
        $data = $this->fractal->createData($resource)->toArray();

        return $data['data'];
    }

    private function getEntityMapper()
    {
        switch($this->entity)
        {
            case 'client_csv':
                return $this->clientMapper;
                break;

            case 'invoice_csv':
                throw new Exception(trans('texts.no_mapper'). ' '. $this->file->getClientOriginalName());

            case 'staff_csv':
                throw new Exception(trans('texts.no_mapper'). ' '. $this->file->getClientOriginalName());

            case 'timesheet_csv':
                throw new Exception(trans('texts.no_mapper'). ' '. $this->file->getClientOriginalName());

            default :
                throw new Exception(trans('texts.no_mapper'). ' '. $this->file->getClientOriginalName());
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: eduardocruz
 * Date: 11/9/15
 * Time: 11:10
 */

namespace App\Ninja\Import\FreshBooks;

use Exception;
use App\Ninja\Import\DataImporterServiceInterface;
use League\Fractal\Manager;
use parseCSV;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use Illuminate\Contracts\Container\Container;

class FreshBooksDataImporterService implements DataImporterServiceInterface
{

    protected $transformer;
    //protected $repository;
    protected $invoiceRepo;


    /**
     * FreshBooksDataImporterService constructor.
     */
    public function __construct(Manager $manager, ClientRepository $clientRepo, InvoiceRepository $invoiceRepo, Container $container)
    {
        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->container = $container;

        $this->fractal = $manager;
        $this->transformerList = array(
            'client'    => __NAMESPACE__ . '\ClientTransformer',
            'invoice'   => __NAMESPACE__ . '\InvoiceTransformer',
            'timesheet'     => __NAMESPACE__ . '\TimesheetTransformer',
        );

        $this->repositoryList = array(
            'client'    =>  '\App\Ninja\Repositories\ClientRepository',
            'invoice'   =>  '\App\Ninja\Repositories\InvoiceRepository',
            'timesheet' =>  '\App\Ninja\Repositories\TaskRepository',
        );
    }

    public function import($files)
    {
        $imported_files = null;

        foreach($files as $entity => $file)
        {
            $imported_files = $imported_files . $this->execute($entity, $file);
        }
        return $imported_files;
    }

    private function execute($entity, $file)
    {
        $this->transformer = $this->createTransformer($entity);
        $this->repository = $this->createRepository($entity);

        $data = $this->parseCSV($file);
        $ignore_header = true;
        try
        {
            $rows = $this->mapCsvToModel($data, $ignore_header);
        } catch(Exception $e)
        {
            throw new Exception($e->getMessage() . ' - ' . $file->getClientOriginalName() );
        }

        $errorMessages = null;

        foreach($rows as $row)
        {
            if($entity=='timesheet')
            {
                $publicId = false;
                $this->repository->save($publicId, $row);
            } else {
                $this->repository->save($row);
            }
        }


        return $file->getClientOriginalName().' '.$errorMessages;
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
            $this->transformer->validateHeader($header);
        }

        $resource = $this->transformer->transform($data);
        $data = $this->fractal->createData($resource)->toArray();

        return $data['data'];
    }

    public function createTransformer($type)
    {
        if (!array_key_exists($type, $this->transformerList)) {
            throw new \InvalidArgumentException("$type is not a valid Transformer");
        }
        $className = $this->transformerList[$type];
        return new $className();
    }

    public function createRepository($type)
    {
        if (!array_key_exists($type, $this->repositoryList)) {
            throw new \InvalidArgumentException("$type is not a valid Repository");
        }
        $className = $this->repositoryList[$type];
        return $this->container->make($className);
        //return new $className();
    }
}
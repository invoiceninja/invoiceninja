<?php namespace app\Http\Controllers;

use Utils;
use View;
use Exception;
use Input;
use Session;
use Redirect;
use App\Services\ImportService;
use App\Http\Controllers\BaseController;

class ImportController extends BaseController
{
    public function __construct(ImportService $importService)
    {
        parent::__construct();

        $this->importService = $importService;
    }

    public function doImport()
    {
        $source = Input::get('source');
        $files = [];

        foreach (ImportService::$entityTypes as $entityType) {
            if (Input::file("{$entityType}_file")) {
                $files[$entityType] = Input::file("{$entityType}_file")->getRealPath();
            }
        }

        try {
            if ($source === IMPORT_CSV) {
                $data = $this->importService->mapCSV($files);
                return View::make('accounts.import_map', ['data' => $data]);
            } else {
                $result = $this->importService->import($source, $files);
                Session::flash('message', trans('texts.imported_file') . ' - ' . $result);
            }
        } catch (Exception $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
    }

    public function doImportCSV()
    {
        $map = Input::get('map');
        $headers = Input::get('headers');

        //try {
            $count = $this->importService->importCSV($map, $headers);
            $message = Utils::pluralize('created_client', $count);

            Session::flash('message', $message);
        //} catch (Exception $exception) {
        //    Session::flash('error', $exception->getMessage());
        //}

        return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
    }
}

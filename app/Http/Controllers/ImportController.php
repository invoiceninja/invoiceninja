<?php namespace app\Http\Controllers;

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
        try {
            $files = [];
            foreach (ImportService::$entityTypes as $entityType) {
                if (Input::file("{$entityType}_file")) {
                    $files[$entityType] = Input::file("{$entityType}_file")->getRealPath();
                }
            }
            $imported_files = $this->importService->import(Input::get('source'), $files);
            Session::flash('message', trans('texts.imported_file').' - '.$imported_files);
        } catch (Exception $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return Redirect::to('/settings/'.ACCOUNT_IMPORT_EXPORT);
    }
}

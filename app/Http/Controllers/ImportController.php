<?php namespace App\Http\Controllers;

use Utils;
use View;
use Exception;
use Input;
use Session;
use Redirect;
use App\Services\ImportService;

class ImportController extends BaseController
{
    public function __construct(ImportService $importService)
    {
        //parent::__construct();

        $this->importService = $importService;
    }

    public function doImport()
    {
        $source = Input::get('source');
        $files = [];

        foreach (ImportService::$entityTypes as $entityType) {
            if (Input::file("{$entityType}_file")) {
                $files[$entityType] = Input::file("{$entityType}_file")->getRealPath();
                if ($source === IMPORT_CSV) {
                    Session::forget("{$entityType}-data");
                }
            }
        }

        if ( ! count($files)) {
            Session::flash('error', trans('texts.select_file'));
            return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
        }

        try {
            if ($source === IMPORT_CSV) {
                $data = $this->importService->mapCSV($files);
                return View::make('accounts.import_map', ['data' => $data]);
            } elseif ($source === IMPORT_JSON) {
                $results = $this->importService->importJSON($files[IMPORT_JSON]);
                return $this->showResult($results);
            } else {
                $results = $this->importService->importFiles($source, $files);
                return $this->showResult($results);
            }
        } catch (Exception $exception) {
            Utils::logError($exception);
            Session::flash('error', $exception->getMessage());
            return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
        }
    }

    public function doImportCSV()
    {
        $map = Input::get('map');
        $headers = Input::get('headers');

        try {
            $results = $this->importService->importCSV($map, $headers);
            return $this->showResult($results);
        } catch (Exception $exception) {
            Utils::logError($exception);
            Session::flash('error', $exception->getMessage());
            return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
        }
    }

    private function showResult($results)
    {
        $message = '';
        $skipped = [];

        foreach ($results as $entityType => $entityResults) {
            if ($count = count($entityResults[RESULT_SUCCESS])) {
                $message .= trans("texts.created_{$entityType}s", ['count' => $count]) . '<br/>';
            }
            if (count($entityResults[RESULT_FAILURE])) {
                $skipped = array_merge($skipped, $entityResults[RESULT_FAILURE]);
            }
        }

        if (count($skipped)) {
            $message .= '<p/>' . trans('texts.failed_to_import') . '<br/>';
            foreach ($skipped as $skip) {
                $message .= json_encode($skip) . '<br/>';
            }
        }

        if ($message) {
            Session::flash('warning', $message);
        }

        return Redirect::to('/settings/' . ACCOUNT_IMPORT_EXPORT);
    }
}

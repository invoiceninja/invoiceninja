<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

class StepsController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function start()
    {
        return view('migration.start');
    }

    public function import()
    {
        return view('migration.import');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function download()
    {
        return view('migration.download');
    }

    /**
     * Handle data downloading for the migration.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleDownload(Request $request)
    {
        $date = date('Y-m-d');

        $output = fopen('php://output', 'w') or Utils::fatalError();

        $fileName = "{$date}-invoiceninja";

        header('Content-Type:application/json');
        header("Content-Disposition:attachment;filename={$fileName}.json");

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        // eager load data, include archived but exclude deleted
        $account = Auth::user()->account;
        $account->load(['clients' => function ($query) {
            $query->withArchived()
                ->with(['contacts', 'invoices' => function ($query) {
                    $query->withArchived()
                        ->with(['invoice_items', 'payments' => function ($query) {
                            $query->withArchived();
                        }]);
                }]);
        }]);

        $resource = new Item($account, new AccountTransformer());
        $data = $manager->parseIncludes('clients.invoices.payments')
            ->createData($resource)
            ->toArray();

        return response()->json($data);
    }
}

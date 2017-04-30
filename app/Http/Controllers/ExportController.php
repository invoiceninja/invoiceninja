<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Task;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use Auth;
use Excel;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

/**
 * Class ExportController.
 */
class ExportController extends BaseController
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function doExport(Request $request)
    {
        $format = $request->input('format');
        $date = date('Y-m-d');

        // set the filename based on the entity types selected
        if ($request->include == 'all') {
            $fileName = "{$date}-invoiceninja";
        } else {
            $fields = $request->all();
            $fields = array_filter(array_map(function ($key) {
                if (! in_array($key, ['format', 'include', '_token'])) {
                    return $key;
                } else {
                    return null;
                }
            }, array_keys($fields), $fields));
            $fileName = $date. '-invoiceninja-' . implode('-', $fields);
        }

        if ($format === 'JSON') {
            return $this->returnJSON($request, $fileName);
        } elseif ($format === 'CSV') {
            return $this->returnCSV($request, $fileName);
        } else {
            return $this->returnXLS($request, $fileName);
        }
    }

    /**
     * @param $request
     * @param $fileName
     *
     * @return \Illuminate\Http\JsonResponse
     */
    private function returnJSON($request, $fileName)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
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

    /**
     * @param $request
     * @param $fileName
     *
     * @return mixed
     */
    private function returnCSV($request, $fileName)
    {
        $data = $this->getData($request);

        return Excel::create($fileName, function ($excel) use ($data) {
            $excel->sheet('', function ($sheet) use ($data) {
                $sheet->loadView('export', $data);
            });
        })->download('csv');
    }

    /**
     * @param $request
     * @param $fileName
     *
     * @return mixed
     */
    private function returnXLS($request, $fileName)
    {
        $user = Auth::user();
        $data = $this->getData($request);

        return Excel::create($fileName, function ($excel) use ($user, $data) {
            $excel->setTitle($data['title'])
                  ->setCreator($user->getDisplayName())
                  ->setLastModifiedBy($user->getDisplayName())
                  ->setDescription('')
                  ->setSubject('')
                  ->setKeywords('')
                  ->setCategory('')
                  ->setManager('')
                  ->setCompany($user->account->getDisplayName());

            foreach ($data as $key => $val) {
                if ($key === 'account' || $key === 'title' || $key === 'multiUser') {
                    continue;
                }
                if ($key === 'recurringInvoices') {
                    $key = 'recurring_invoices';
                }
                $label = trans("texts.{$key}");
                $excel->sheet($label, function ($sheet) use ($key, $data) {
                    if ($key === 'quotes') {
                        $key = 'invoices';
                        $data['entityType'] = ENTITY_QUOTE;
                        $data['invoices'] = $data['quotes'];
                    }
                    $sheet->loadView("export.{$key}", $data);
                });
            }
        })->download('xls');
    }

    /**
     * @param $request
     *
     * @return array
     */
    private function getData($request)
    {
        $account = Auth::user()->account;

        $data = [
            'account' => $account,
            'title' => 'Invoice Ninja v' . NINJA_VERSION . ' - ' . $account->formatDateTime($account->getDateTime()),
            'multiUser' => $account->users->count() > 1,
        ];

        if ($request->input('include') === 'all' || $request->input('clients')) {
            $data['clients'] = Client::scope()
                ->with('user', 'contacts', 'country')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('contacts')) {
            $data['contacts'] = Contact::scope()
                ->with('user', 'client.contacts')
                ->withTrashed()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('credits')) {
            $data['credits'] = Credit::scope()
                ->with('user', 'client.contacts')
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('tasks')) {
            $data['tasks'] = Task::scope()
                ->with('user', 'client.contacts')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('invoices')) {
            $data['invoices'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('user', 'client.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_recurring', '=', false)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('quotes')) {
            $data['quotes'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_QUOTE)
                ->with('user', 'client.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_recurring', '=', false)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('recurring')) {
            $data['recurringInvoices'] = Invoice::scope()
                ->invoiceType(INVOICE_TYPE_STANDARD)
                ->with('user', 'client.contacts', 'invoice_status', 'frequency')
                ->withArchived()
                ->where('is_recurring', '=', true)
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('payments')) {
            $data['payments'] = Payment::scope()
                ->withArchived()
                ->with('user', 'client.contacts', 'payment_type', 'invoice', 'account_gateway.gateway')
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('expenses')) {
            $data['expenses'] = Expense::scope()
                ->with('user', 'vendor.vendor_contacts', 'client.contacts', 'expense_category')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('products')) {
            $data['products'] = Product::scope()
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('vendors')) {
            $data['vendors'] = Vendor::scope()
                ->with('user', 'vendor_contacts', 'country')
                ->withArchived()
                ->get();
        }

        if ($request->input('include') === 'all' || $request->input('vendor_contacts')) {
            $data['vendor_contacts'] = VendorContact::scope()
                ->with('user', 'vendor.vendor_contacts')
                ->withTrashed()
                ->get();
        }

        return $data;
    }
}

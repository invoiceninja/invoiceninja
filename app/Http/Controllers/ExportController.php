<?php namespace App\Http\Controllers;

use Auth;
use Excel;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Vendor;
use App\Models\VendorContact;

class ExportController extends BaseController
{
    public function doExport(Request $request)
    {
        $format = $request->input('format');
        $date = date('Y-m-d');
        $fileName = "invoice-ninja-{$date}";

        if ($format === 'JSON') {
            return $this->returnJSON($request, $fileName);
        } elseif ($format === 'CSV') {
            return $this->returnCSV($request, $fileName);
        } else {
            return $this->returnXLS($request, $fileName);
        }
    }

    private function returnJSON($request, $fileName)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/json');
        header("Content-Disposition:attachment;filename={$fileName}.json");

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        $account = Auth::user()->account;
        $account->loadAllData();

        $resource = new Item($account, new AccountTransformer);
        $data = $manager->createData($resource)->toArray();

        return response()->json($data);
    }


    private function returnCSV($request, $fileName)
    {
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($data) {
            $excel->sheet('', function($sheet) use ($data) {
                $sheet->loadView('export', $data);
            });
        })->download('csv');
    }

    private function returnXLS($request, $fileName)
    {
        $user = Auth::user();
        $data = $this->getData($request);

        return Excel::create($fileName, function($excel) use ($user, $data) {

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
                $label = trans("texts.{$key}");
                $excel->sheet($label, function($sheet) use ($key, $data) {
                    if ($key === 'quotes') {
                        $key = 'invoices';
                        $data['entityType'] = ENTITY_QUOTE;
                    } elseif ($key === 'recurringInvoices') {
                        $key = 'recurring_invoices';
                    }
                    $sheet->loadView("export.{$key}", $data);
                });
            }
        })->download('xls');
    }

    private function getData($request)
    {
        $account = Auth::user()->account;

        $data = [
            'account' => $account,
            'title' => 'Invoice Ninja v' . NINJA_VERSION . ' - ' . $account->formatDateTime($account->getDateTime()),
            'multiUser' => $account->users->count() > 1
        ];
        
        if ($request->input(ENTITY_CLIENT)) {
            $data['clients'] = Client::scope()
                ->with('user', 'contacts', 'country')
                ->withArchived()
                ->get();

            $data['contacts'] = Contact::scope()
                ->with('user', 'client.contacts')
                ->withTrashed()
                ->get();

            $data['credits'] = Credit::scope()
                ->with('user', 'client.contacts')
                ->get();
        }
        
        if ($request->input(ENTITY_TASK)) {
            $data['tasks'] = Task::scope()
                ->with('user', 'client.contacts')
                ->withArchived()
                ->get();
        }
        
        if ($request->input(ENTITY_INVOICE)) {
            $data['invoices'] = Invoice::scope()
                ->with('user', 'client.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_quote', '=', false)
                ->where('is_recurring', '=', false)
                ->get();
        
            $data['quotes'] = Invoice::scope()
                ->with('user', 'client.contacts', 'invoice_status')
                ->withArchived()
                ->where('is_quote', '=', true)
                ->where('is_recurring', '=', false)
                ->get();

            $data['recurringInvoices'] = Invoice::scope()
                ->with('user', 'client.contacts', 'invoice_status', 'frequency')
                ->withArchived()
                ->where('is_quote', '=', false)
                ->where('is_recurring', '=', true)
                ->get();
        }
        
        if ($request->input(ENTITY_PAYMENT)) {
            $data['payments'] = Payment::scope()
                ->withArchived()
                ->with('user', 'client.contacts', 'payment_type', 'invoice', 'account_gateway.gateway')
                ->get();
        }

        
        if ($request->input(ENTITY_VENDOR)) {
            $data['clients'] = Vendor::scope()
                ->with('user', 'vendor_contacts', 'country')
                ->withArchived()
                ->get();

            $data['vendor_contacts'] = VendorContact::scope()
                ->with('user', 'vendor.vendor_contacts')
                ->withTrashed()
                ->get();
            
            /*
            $data['expenses'] = Credit::scope()
                ->with('user', 'client.contacts')
                ->get();
            */
        }
        
        return $data;
    }
}
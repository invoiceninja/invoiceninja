<?php namespace App\Http\Controllers;
// vendor
use Auth;
use DB;
use Input;
use Utils;
use Datatable;
use App\Models\VendorInvitation;
use App\Ninja\Repositories\VendorActivityRepository;

class PublicVendorController extends BaseController
{
    private $activityRepo;
    private $vendor;
    
    public function __construct(VendorActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
        $this->vendor = $activityRepo->vendor;        
    }

    public function dashboard()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $this->vendor,
            'hideLogo' => $account->isWhiteLabel(),
            'clientViewCSS' => $account->clientViewCSS(),
        ];

        return response()->view('invited.dashboard', $data);
    }

    public function activityDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }
        

        $query = $this->activityRepo->findByClientId($invoice->client_id);
        $query->where('vendor_activities.adjustment', '!=', 0);

        return Datatable::query($query)
            ->addColumn('vendor_activities.id', function ($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('activity_type_id', function ($model) {
                $data = [
                    'client' => Utils::getClientDisplayName($model),
                    'user' => $model->is_system ? ('<i>' . trans('texts.system') . '</i>') : ($model->user_first_name . ' ' . $model->user_last_name), 
                    'invoice' => trans('texts.invoice') . ' ' . $model->invoice,
                    'contact' => Utils::getClientDisplayName($model),
                    'payment' => trans('texts.payment') . ($model->payment ? ' ' . $model->payment : ''),
                ];

                return trans("texts.activity_{$model->activity_type_id}", $data);
             })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : ''; })
            ->make();
    }

    public function invoiceIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => $account->isWhiteLabel(),
            'clientViewCSS' => $account->clientViewCSS(),
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'columns' => Utils::trans(['invoice_number', 'invoice_date', 'invoice_total', 'balance_due', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return '';
        }

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_INVOICE, Input::get('sSearch'));
    }


    public function paymentIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => $account->isWhiteLabel(),
            'clientViewCSS' => $account->clientViewCSS(),
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'payment_amount', 'payment_date'])
        ];

        return response()->view('public_list', $data);
    }

    public function paymentDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }
        $payments = $this->paymentRepo->findForContact($invitation->contact->id, Input::get('sSearch'));

        return Datatable::query($payments)
                ->addColumn('invoice_number', function ($model) { return $model->invitation_key ? link_to('/view/'.$model->invitation_key, $model->invoice_number) : $model->invoice_number; })
                ->addColumn('transaction_reference', function ($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; })
                ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); })
                ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
                ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
                ->make();
    }

    public function quoteIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
          'color' => $color,
          'hideLogo' => $account->isWhiteLabel(),
          'clientViewCSS' => $account->clientViewCSS(),
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['quote_number', 'quote_date', 'quote_total', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }


    public function quoteDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_QUOTE, Input::get('sSearch'));
    }

    private function returnError()
    {
        return response()->view('error', [
            'error' => trans('texts.invoice_not_found'),
            'hideHeader' => true,
            'clientViewCSS' => $account->clientViewCSS(),
        ]);
    }

    private function getInvitation()
    {
        $invitationKey = session('invitation_key');

        if (!$invitationKey) {
            return false;
        }

        $invitation = VendorInvitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation || $invitation->is_deleted) {
            return false;
        }

        $invoice = $invitation->invoice;

        if (!$invoice || $invoice->is_deleted) {
            return false;
        }

        return $invitation;
    }

}
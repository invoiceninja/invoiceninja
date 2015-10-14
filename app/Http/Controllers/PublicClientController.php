<?php namespace App\Http\Controllers;

use Auth;
use DB;
use Input;
use Utils;
use Datatable;
use App\Models\Invitation;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;

class PublicClientController extends BaseController
{
    private $invoiceRepo;
    private $paymentRepo;

    public function __construct(InvoiceRepository $invoiceRepo, PaymentRepository $paymentRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
    }

    public function dashboard()
    {
        $invitation = $this->getInvitation();
        $account = $invitation->account;
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $client,
            'hideLogo' => $account->isWhiteLabel(),
        ];

        return response()->view('invited.dashboard', $data);
    }

    public function activityDatatable()
    {
        $invitation = $this->getInvitation();
        $invoice = $invitation->invoice;

        $query = DB::table('activities')
                    ->join('clients', 'clients.id', '=', 'activities.client_id')
                    ->where('activities.client_id', '=', $invoice->client_id)
                    ->where('activities.adjustment', '!=', 0)
                    ->select('activities.id', 'activities.message', 'activities.created_at', 'clients.currency_id', 'activities.balance', 'activities.adjustment');

        return Datatable::query($query)
            ->addColumn('activities.id', function ($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('message', function ($model) { return strip_tags(Utils::decodeActivity($model->message)); })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id) : ''; })
            ->make();
    }

    public function invoiceIndex()
    {
        $invitation = $this->getInvitation();
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => $account->isWhiteLabel(),
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'columns' => Utils::trans(['invoice_number', 'invoice_date', 'invoice_total', 'balance_due', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceDatatable()
    {
        $invitation = $this->getInvitation();

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_INVOICE, Input::get('sSearch'));
    }


    public function paymentIndex()
    {
        $invitation = $this->getInvitation();
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'hideLogo' => $account->isWhiteLabel(),
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'payment_amount', 'payment_date'])
        ];

        return response()->view('public_list', $data);
    }

    public function paymentDatatable()
    {
        $invitation = $this->getInvitation();
        $payments = $this->paymentRepo->findForContact($invitation->contact->id, Input::get('sSearch'));

        return Datatable::query($payments)
                ->addColumn('invoice_number', function ($model) { return $model->invitation_key ? link_to('/view/'.$model->invitation_key, $model->invoice_number) : $model->invoice_number; })
                ->addColumn('transaction_reference', function ($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; })
                ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); })
                ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
                ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
                ->make();
    }

    public function quoteIndex()
    {
        $invitation = $this->getInvitation();
        $account = $invitation->account;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
          'color' => $color,
          'hideLogo' => $account->isWhiteLabel(),
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['quote_number', 'quote_date', 'quote_total', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }


    public function quoteDatatable()
    {
        $invitation = $this->getInvitation();

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_QUOTE, Input::get('sSearch'));
    }

    private function getInvitation()
    {
        $invitationKey = session('invitation_key');

        if (!$invitationKey) {
            app()->abort(404);
        }

        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation || $invitation->is_deleted) {
            app()->abort(404);
        }

        $invoice = $invitation->invoice;

        if (!$invoice || $invoice->is_deleted) {
            app()->abort(404);
        }

        return $invitation;
    }

}
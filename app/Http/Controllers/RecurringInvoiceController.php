<?php namespace App\Http\Controllers;

use Utils;
use App\Ninja\Repositories\InvoiceRepository;

/**
 * Class RecurringInvoiceController
 */
class RecurringInvoiceController extends BaseController
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * RecurringInvoiceController constructor.
     * @param InvoiceRepository $invoiceRepo
     */
    public function __construct(InvoiceRepository $invoiceRepo)
    {
        //parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $data = [
            'title' => trans('texts.recurring_invoices'),
            'entityType' => ENTITY_RECURRING_INVOICE,
            'columns' => Utils::trans([
                'checkbox',
                'frequency',
                'client',
                'start_date',
                'end_date',
                'invoice_total',
                'action'
            ])
        ];

        return response()->view('list', $data);
    }

}
<?php

namespace App\Http\Controllers;

use App\Ninja\Datatables\RecurringInvoiceDatatable;
use App\Ninja\Repositories\InvoiceRepository;

/**
 * Class RecurringQuoteController.
 */
class RecurringQuoteController extends BaseController
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * RecurringQuoteController constructor.
     *
     * @param InvoiceRepository $invoiceRepo
     */
    public function __construct(InvoiceRepository $invoiceRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        $data = [
            'title' => trans('texts.recurring_quotes'),
            'entityType' => ENTITY_RECURRING_QUOTE,
            'datatable' => new RecurringInvoiceDatatable(true, false, ENTITY_RECURRING_QUOTE),
        ];

        return response()->view('list_wrapper', $data);
    }
}

<?php

namespace App\Services;

use App\Ninja\Datatables\RecurringInvoiceDatatable;
use App\Ninja\Repositories\InvoiceRepository;
use Auth;
use Utils;

class RecurringInvoiceService extends BaseService
{
    protected $invoiceRepo;
    protected $datatableService;

    public function __construct(InvoiceRepository $invoiceRepo, DatatableService $datatableService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($accountId, $clientPublicId, $entityType, $search)
    {
        $datatable = new RecurringInvoiceDatatable(true, $clientPublicId);
        $query = $this->invoiceRepo->getRecurringInvoices($accountId, $clientPublicId, $search);

        if (! Utils::hasPermission('view_all')) {
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}

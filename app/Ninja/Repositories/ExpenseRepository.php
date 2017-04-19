<?php

namespace App\Ninja\Repositories;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Vendor;
use Auth;
use DB;
use Utils;

class ExpenseRepository extends BaseRepository
{
    protected $documentRepo;

    // Expenses
    public function getClassName()
    {
        return 'App\Models\Expense';
    }

    public function __construct(DocumentRepository $documentRepo)
    {
        $this->documentRepo = $documentRepo;
    }

    public function all()
    {
        return Expense::scope()
                ->with('user')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function findVendor($vendorPublicId)
    {
        $vendorId = Vendor::getPrivateId($vendorPublicId);

        $query = $this->find()->where('expenses.vendor_id', '=', $vendorId);

        return $query;
    }

    public function find($filter = null)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'expenses.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftjoin('vendors', 'vendors.id', '=', 'expenses.vendor_id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'expenses.invoice_id')
                    ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('vendors.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->where(function ($query) { // handle when client isn't set
                        $query->where('contacts.is_primary', '=', true)
                              ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->select(
                        DB::raw('COALESCE(expenses.invoice_id, expenses.should_be_invoiced) status'),
                        'expenses.account_id',
                        'expenses.amount',
                        'expenses.deleted_at',
                        'expenses.exchange_rate',
                        'expenses.expense_date as expense_date_sql',
                        DB::raw("CONCAT(expenses.expense_date, expenses.created_at) as expense_date"),
                        'expenses.id',
                        'expenses.is_deleted',
                        'expenses.private_notes',
                        'expenses.public_id',
                        'expenses.invoice_id',
                        'expenses.public_notes',
                        'expenses.should_be_invoiced',
                        'expenses.vendor_id',
                        'expenses.expense_currency_id',
                        'expenses.invoice_currency_id',
                        'expenses.user_id',
                        'expenses.tax_rate1',
                        'expenses.tax_rate2',
                        'expenses.payment_date',
                        'expense_categories.name as category',
                        'expense_categories.user_id as category_user_id',
                        'expense_categories.public_id as category_public_id',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'invoices.balance',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'vendors.user_id as vendor_user_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'contacts.first_name',
                        'contacts.email',
                        'contacts.last_name',
                        'clients.country_id as client_country_id'
                    );

        $this->applyFilters($query, ENTITY_EXPENSE);

        if ($statuses = session('entity_status_filter:' . ENTITY_EXPENSE)) {
            $statuses = explode(',', $statuses);
            $query->where(function ($query) use ($statuses) {
                $query->whereNull('expenses.id');

                if (in_array(EXPENSE_STATUS_LOGGED, $statuses)) {
                    $query->orWhere('expenses.invoice_id', '=', 0)
                          ->orWhereNull('expenses.invoice_id');
                }
                if (in_array(EXPENSE_STATUS_INVOICED, $statuses)) {
                    $query->orWhere('expenses.invoice_id', '>', 0);
                    if (! in_array(EXPENSE_STATUS_BILLED, $statuses)) {
                        $query->where('invoices.balance', '>', 0);
                    }
                }
                if (in_array(EXPENSE_STATUS_BILLED, $statuses)) {
                    $query->orWhere('invoices.balance', '=', 0)
                          ->where('expenses.invoice_id', '>', 0);
                }
                if (in_array(EXPENSE_STATUS_PAID, $statuses)) {
                    $query->orWhereNotNull('expenses.payment_date');
                }
                if (in_array(EXPENSE_STATUS_UNPAID, $statuses)) {
                    $query->orWhereNull('expenses.payment_date');
                }
                if (in_array(EXPENSE_STATUS_PENDING, $statuses)) {
                    $query->orWhere('expenses.should_be_invoiced', '=', 1)
                            ->whereNull('expenses.invoice_id');
                }
            });
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expenses.public_notes', 'like', '%'.$filter.'%')
                      ->orWhere('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('vendors.name', 'like', '%'.$filter.'%')
                      ->orWhere('expense_categories.name', 'like', '%'.$filter.'%');
                ;
            });
        }

        return $query;
    }

    public function save($input, $expense = null)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($expense) {
            // do nothing
        } elseif ($publicId) {
            $expense = Expense::scope($publicId)->firstOrFail();
            if (Utils::isNinjaDev()) {
                \Log::warning('Entity not set in expense repo save');
            }
        } else {
            $expense = Expense::createNew();
        }

        if ($expense->is_deleted) {
            return $expense;
        }

        // First auto fill
        $expense->fill($input);

        if (isset($input['expense_date'])) {
            $expense->expense_date = Utils::toSqlDate($input['expense_date']);
        }
        if (isset($input['payment_date'])) {
            $expense->payment_date = Utils::toSqlDate($input['payment_date']);
        }

        $expense->should_be_invoiced = isset($input['should_be_invoiced']) && floatval($input['should_be_invoiced']) || $expense->client_id ? true : false;

        if (! $expense->expense_currency_id) {
            $expense->expense_currency_id = \Auth::user()->account->getCurrencyId();
        }
        if (! $expense->invoice_currency_id) {
            $expense->invoice_currency_id = \Auth::user()->account->getCurrencyId();
        }

        $rate = isset($input['exchange_rate']) ? Utils::parseFloat($input['exchange_rate']) : 1;
        $expense->exchange_rate = round($rate, 4);
        if (isset($input['amount'])) {
            $expense->amount = round(Utils::parseFloat($input['amount']), 2);
        }

        $expense->save();

        // Documents
        $document_ids = ! empty($input['document_ids']) ? array_map('intval', $input['document_ids']) : [];
        ;
        foreach ($document_ids as $document_id) {
            // check document completed upload before user submitted form
            if ($document_id) {
                $document = Document::scope($document_id)->first();
                if ($document && Auth::user()->can('edit', $document)) {
                    $document->invoice_id = null;
                    $document->expense_id = $expense->id;
                    $document->save();
                }
            }
        }

        // prevent loading all of the documents if we don't have to
        if (! $expense->wasRecentlyCreated) {
            foreach ($expense->documents as $document) {
                if (! in_array($document->public_id, $document_ids)) {
                    // Not checking permissions; deleting a document is just editing the invoice
                    $document->delete();
                }
            }
        }

        return $expense;
    }
}

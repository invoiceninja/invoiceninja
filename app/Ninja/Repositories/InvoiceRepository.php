<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use Session;
use Auth;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Invitation;
use App\Models\Product;
use App\Models\Task;
use App\Models\Document;
use App\Models\Expense;
use App\Services\PaymentService;
use App\Ninja\Repositories\BaseRepository;

class InvoiceRepository extends BaseRepository
{
    protected $documentRepo;

    public function getClassName()
    {
        return 'App\Models\Invoice';
    }

    public function __construct(PaymentService $paymentService, DocumentRepository $documentRepo)
    {
        $this->documentRepo = $documentRepo;
        $this->paymentService = $paymentService;
    }

    public function all()
    {
        return Invoice::scope()
                ->with('user', 'client.contacts', 'invoice_status')
                ->withTrashed()
                ->where('is_quote', '=', false)
                ->where('is_recurring', '=', false)
                ->get();
    }

    public function getInvoices($accountId, $clientPublicId = false, $entityType = ENTITY_INVOICE, $filter = false)
    {
        $query = DB::table('invoices')
            ->join('accounts', 'accounts.id', '=', 'invoices.account_id')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->join('invoice_statuses', 'invoice_statuses.id', '=', 'invoices.invoice_status_id')
            ->join('contacts', 'contacts.client_id', '=', 'clients.id')
            ->where('invoices.account_id', '=', $accountId)
            ->where('clients.deleted_at', '=', null)
            ->where('contacts.deleted_at', '=', null)
            ->where('invoices.is_recurring', '=', false)
            ->where('contacts.is_primary', '=', true)
            ->select(
                DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                'clients.public_id as client_public_id',
                'clients.user_id as client_user_id',
                'invoice_number',
                'invoice_status_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'invoices.public_id',
                'invoices.amount',
                'invoices.balance',
                'invoices.invoice_date',
                'invoices.due_date',
                'invoice_statuses.name as invoice_status_name',
                'contacts.first_name',
                'contacts.last_name',
                'contacts.email',
                'invoices.quote_id',
                'invoices.quote_invoice_id',
                'invoices.deleted_at',
                'invoices.is_deleted',
                'invoices.partial',
                'invoices.user_id'
            );

        if (!\Session::get('show_trash:'.$entityType)) {
            $query->where('invoices.deleted_at', '=', null);
        }

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%')
                      ->orWhere('invoice_statuses.name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                  ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getRecurringInvoices($accountId, $clientPublicId = false, $filter = false)
    {
        $query = DB::table('invoices')
                    ->join('accounts', 'accounts.id', '=', 'invoices.account_id')
                    ->join('clients', 'clients.id', '=', 'invoices.client_id')
                    ->join('frequencies', 'frequencies.id', '=', 'invoices.frequency_id')
                    ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', $accountId)
                    ->where('invoices.is_quote', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', true)
                    ->where('contacts.is_primary', '=', true)
                    ->where('clients.deleted_at', '=', null)
                    ->select(
                        DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                        'clients.public_id as client_public_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'invoices.public_id',
                        'invoices.amount',
                        'frequencies.name as frequency',
                        'invoices.start_date',
                        'invoices.end_date',
                        'contacts.first_name',
                        'contacts.last_name',
                        'contacts.email',
                        'invoices.deleted_at',
                        'invoices.is_deleted',
                        'invoices.user_id'
                    );

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if (!\Session::get('show_trash:recurring_invoice')) {
            $query->where('invoices.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('invoices.invoice_number', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function getClientDatatable($contactId, $entityType, $search)
    {
        $query = DB::table('invitations')
          ->join('accounts', 'accounts.id', '=', 'invitations.account_id')
          ->join('invoices', 'invoices.id', '=', 'invitations.invoice_id')
          ->join('clients', 'clients.id', '=', 'invoices.client_id')
          ->join('contacts', 'contacts.client_id', '=', 'clients.id')
          ->where('invitations.contact_id', '=', $contactId)
          ->where('invitations.deleted_at', '=', null)
          ->where('invoices.is_quote', '=', $entityType == ENTITY_QUOTE)
          ->where('invoices.is_deleted', '=', false)
          ->where('clients.deleted_at', '=', null)
          ->where('contacts.deleted_at', '=', null)
          ->where('contacts.is_primary', '=', true)
          ->where('invoices.is_recurring', '=', false)
          // This needs to be a setting to also hide the activity on the dashboard page
          //->where('invoices.invoice_status_id', '>=', INVOICE_STATUS_SENT)
          ->select(
                DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
                DB::raw('COALESCE(clients.country_id, accounts.country_id) country_id'),
                'invitations.invitation_key',
                'invoices.invoice_number',
                'invoices.invoice_date',
                'invoices.balance as balance',
                'invoices.due_date',
                'clients.public_id as client_public_id',
                DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                'invoices.public_id',
                'invoices.amount',
                'invoices.start_date',
                'invoices.end_date',
                'invoices.partial'
            );

        $table = \Datatable::query($query)
            ->addColumn('invoice_number', function ($model) use ($entityType) { return link_to('/view/'.$model->invitation_key, $model->invoice_number)->toHtml(); })
            ->addColumn('invoice_date', function ($model) { return Utils::fromSqlDate($model->invoice_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); });

        if ($entityType == ENTITY_INVOICE) {
            $table->addColumn('balance', function ($model) {
                return $model->partial > 0 ?
                    trans('texts.partial_remaining', [
                        'partial' => Utils::formatMoney($model->partial, $model->currency_id, $model->country_id),
                        'balance' => Utils::formatMoney($model->balance, $model->currency_id, $model->country_id)
                    ]) :
                    Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
            });
        }

        return $table->addColumn('due_date', function ($model) { return Utils::fromSqlDate($model->due_date); })
            ->make();
    }

    public function save($data, $invoice = null)
    {
        $account = \Auth::user()->account;
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        $isNew = !$publicId || $publicId == '-1';

        if ($invoice) {
            // do nothing
        } elseif ($isNew) {
            $entityType = ENTITY_INVOICE;
            if (isset($data['is_recurring']) && filter_var($data['is_recurring'], FILTER_VALIDATE_BOOLEAN)) {
                $entityType = ENTITY_RECURRING_INVOICE;
            } elseif (isset($data['is_quote']) && filter_var($data['is_quote'], FILTER_VALIDATE_BOOLEAN)) {
                $entityType = ENTITY_QUOTE;
            }
            $invoice = $account->createInvoice($entityType, $data['client_id']);
            if (isset($data['has_tasks']) && filter_var($data['has_tasks'], FILTER_VALIDATE_BOOLEAN)) {
                $invoice->has_tasks = true;
            }
            if (isset($data['has_expenses']) && filter_var($data['has_expenses'], FILTER_VALIDATE_BOOLEAN)) {
                $invoice->has_expenses = true;
            }
        } else {
            $invoice = Invoice::scope($publicId)->firstOrFail();
            \Log::warning('Entity not set in invoice repo save');
        }

        $invoice->fill($data);

        if ((isset($data['set_default_terms']) && $data['set_default_terms'])
            || (isset($data['set_default_footer']) && $data['set_default_footer'])) {
            if (isset($data['set_default_terms']) && $data['set_default_terms']) {
                $account->{"{$invoice->getEntityType()}_terms"} = trim($data['terms']);
            }
            if (isset($data['set_default_footer']) && $data['set_default_footer']) {
                $account->invoice_footer = trim($data['invoice_footer']);
            }
            $account->save();
        }

        if (isset($data['invoice_number']) && !$invoice->is_recurring) {
            $invoice->invoice_number = trim($data['invoice_number']);
        }

        if (isset($data['discount'])) {
            $invoice->discount = round(Utils::parseFloat($data['discount']), 2);
        }
        if (isset($data['is_amount_discount'])) {
            $invoice->is_amount_discount = $data['is_amount_discount'] ? true : false;
        }
        if (isset($data['partial'])) {
            $invoice->partial = round(Utils::parseFloat($data['partial']), 2);
        }
        if (isset($data['invoice_date_sql'])) {
            $invoice->invoice_date = $data['invoice_date_sql'];
        } elseif (isset($data['invoice_date'])) {
            $invoice->invoice_date = Utils::toSqlDate($data['invoice_date']);
        }

        if(isset($data['invoice_status_id'])) {
            if($data['invoice_status_id'] == 0) {
                $data['invoice_status_id'] = INVOICE_STATUS_DRAFT;
            }
            $invoice->invoice_status_id = $data['invoice_status_id'];
        }

        if ($invoice->is_recurring) {
            if ($invoice->start_date && $invoice->start_date != Utils::toSqlDate($data['start_date'])) {
                $invoice->last_sent_date = null;
            }

            $invoice->frequency_id = $data['frequency_id'] ? $data['frequency_id'] : 0;
            $invoice->start_date = Utils::toSqlDate($data['start_date']);
            $invoice->end_date = Utils::toSqlDate($data['end_date']);
            $invoice->auto_bill = isset($data['auto_bill']) && $data['auto_bill'] ? true : false;

            if (isset($data['recurring_due_date'])) {
                $invoice->due_date = $data['recurring_due_date'];
            } elseif (isset($data['due_date'])) {
                $invoice->due_date = $data['due_date'];
            }
        } else {
            if (isset($data['due_date']) || isset($data['due_date_sql'])) {
                $invoice->due_date = isset($data['due_date_sql']) ? $data['due_date_sql'] : Utils::toSqlDate($data['due_date']);
            }
            $invoice->frequency_id = 0;
            $invoice->start_date = null;
            $invoice->end_date = null;
        }

        if (isset($data['terms']) && trim($data['terms'])) {
            $invoice->terms = trim($data['terms']);
        } elseif ($isNew && $account->{"{$entityType}_terms"}) {
            $invoice->terms = $account->{"{$entityType}_terms"};
        } else {
            $invoice->terms = '';
        }

        $invoice->invoice_footer = (isset($data['invoice_footer']) && trim($data['invoice_footer'])) ? trim($data['invoice_footer']) : (!$publicId && $account->invoice_footer ? $account->invoice_footer : '');
        $invoice->public_notes = isset($data['public_notes']) ? trim($data['public_notes']) : null;

        // process date variables if not recurring
        if(!$invoice->is_recurring) {
            $invoice->terms = Utils::processVariables($invoice->terms);
            $invoice->invoice_footer = Utils::processVariables($invoice->invoice_footer);
            $invoice->public_notes = Utils::processVariables($invoice->public_notes);
        }

        if (isset($data['po_number'])) {
            $invoice->po_number = trim($data['po_number']);
        }

        $invoice->invoice_design_id = isset($data['invoice_design_id']) ? $data['invoice_design_id'] : $account->invoice_design_id;

        // provide backwards compatability
        if (isset($data['tax_name']) && isset($data['tax_rate'])) {
            $data['tax_name1'] = $data['tax_name'];
            $data['tax_rate1'] = $data['tax_rate'];
        }

        $total = 0;
        $itemTax = 0;

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            if (!$item['cost'] && !$item['product_key'] && !$item['notes']) {
                continue;
            }

            $invoiceItemCost = round(Utils::parseFloat($item['cost']), 2);
            $invoiceItemQty = round(Utils::parseFloat($item['qty']), 2);

            $lineTotal = $invoiceItemCost * $invoiceItemQty;
            $total += round($lineTotal, 2);
        }

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            $invoiceItemCost = round(Utils::parseFloat($item['cost']), 2);
            $invoiceItemQty = round(Utils::parseFloat($item['qty']), 2);
            $lineTotal = $invoiceItemCost * $invoiceItemQty;

            if ($invoice->discount > 0) {
                if ($invoice->is_amount_discount) {
                    $lineTotal -= round(($lineTotal/$total) * $invoice->discount, 2);
                } else {
                    $lineTotal -= round($lineTotal * ($invoice->discount/100), 2);
                }
            }

            if (isset($item['tax_rate1']) && Utils::parseFloat($item['tax_rate1']) > 0) {
                $invoiceItemTaxRate = Utils::parseFloat($item['tax_rate1']);
                $itemTax += round($lineTotal * $invoiceItemTaxRate / 100, 2);
            }
            if (isset($item['tax_rate2']) && Utils::parseFloat($item['tax_rate2']) > 0) {
                $invoiceItemTaxRate = Utils::parseFloat($item['tax_rate2']);
                $itemTax += round($lineTotal * $invoiceItemTaxRate / 100, 2);
            }
        }

        if ($invoice->discount > 0) {
            if ($invoice->is_amount_discount) {
                $total -= $invoice->discount;
            } else {
                $total *= (100 - $invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if (isset($data['custom_value1'])) {
            $invoice->custom_value1 = round($data['custom_value1'], 2);
            if ($isNew) {
                $invoice->custom_taxes1 = $account->custom_invoice_taxes1 ?: false;
            }
        }
        if (isset($data['custom_value2'])) {
            $invoice->custom_value2 = round($data['custom_value2'], 2);
            if ($isNew) {
                $invoice->custom_taxes2 = $account->custom_invoice_taxes2 ?: false;
            }
        }

        if (isset($data['custom_text_value1'])) {
            $invoice->custom_text_value1 = trim($data['custom_text_value1']);
        }
        if (isset($data['custom_text_value2'])) {
            $invoice->custom_text_value2 = trim($data['custom_text_value2']);
        }

        // custom fields charged taxes
        if ($invoice->custom_value1 && $invoice->custom_taxes1) {
            $total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && $invoice->custom_taxes2) {
            $total += $invoice->custom_value2;
        }

        $taxAmount1 = round($total * $invoice->tax_rate1 / 100, 2);
        $taxAmount2 = round($total * $invoice->tax_rate2 / 100, 2);
        $total = round($total + $taxAmount1 + $taxAmount2, 2);
        $total += $itemTax;

        // custom fields not charged taxes
        if ($invoice->custom_value1 && !$invoice->custom_taxes1) {
            $total += $invoice->custom_value1;
        }
        if ($invoice->custom_value2 && !$invoice->custom_taxes2) {
            $total += $invoice->custom_value2;
        }

        if ($publicId) {
            $invoice->balance = $total - ($invoice->amount - $invoice->balance);
        } else {
            $invoice->balance = $total;
        }

        $invoice->amount = $total;
        $invoice->save();

        if ($publicId) {
            $invoice->invoice_items()->forceDelete();
        }

        $document_ids = !empty($data['document_ids'])?array_map('intval', $data['document_ids']):array();;
        foreach ($document_ids as $document_id){
            $document = Document::scope($document_id)->first();
            if($document && Auth::user()->can('edit', $document)){

                if($document->invoice_id && $document->invoice_id != $invoice->id){
                    // From a clone
                    $document = $document->cloneDocument();
                    $document_ids[] = $document->public_id;// Don't remove this document
                }

                $document->invoice_id = $invoice->id;
                $document->expense_id = null;
                $document->save();
            }
        }

        if(!empty($data['documents']) && Auth::user()->can('create', ENTITY_DOCUMENT)){
            // Fallback upload
            $doc_errors = array();
            foreach($data['documents'] as $upload){
                $result = $this->documentRepo->upload($upload);
                if(is_string($result)){
                    $doc_errors[] = $result;
                }
                else{
                    $result->invoice_id = $invoice->id;
                    $result->save();
                    $document_ids[] = $result->public_id;
                }
            }
            if(!empty($doc_errors)){
                Session::flash('error', implode('<br>',array_map('htmlentities',$doc_errors)));
            }
        }

        foreach ($invoice->documents as $document){
            if(!in_array($document->public_id, $document_ids)){
                // Removed
                // Not checking permissions; deleting a document is just editing the invoice
                if($document->invoice_id == $invoice->id){
                    // Make sure the document isn't on a clone
                    $document->delete();
                }
            }
        }

        foreach ($data['invoice_items'] as $item) {
            $item = (array) $item;
            if (empty($item['cost']) && empty($item['product_key']) && empty($item['notes']) && empty($item['custom_value1']) && empty($item['custom_value2'])) {
                continue;
            }

            $task = false;
            if (isset($item['task_public_id']) && $item['task_public_id']) {
                $task = Task::scope($item['task_public_id'])->where('invoice_id', '=', null)->firstOrFail();
                if(Auth::user()->can('edit', $task)){
                    $task->invoice_id = $invoice->id;
                    $task->client_id = $invoice->client_id;
                    $task->save();
                }
            }

            $expense = false;
            if (isset($item['expense_public_id']) && $item['expense_public_id']) {
                $expense = Expense::scope($item['expense_public_id'])->where('invoice_id', '=', null)->firstOrFail();
                if(Auth::user()->can('edit', $expense)){
                    $expense->invoice_id = $invoice->id;
                    $expense->client_id = $invoice->client_id;
                    $expense->save();
                }
            }

            if ($productKey = trim($item['product_key'])) {
                if (\Auth::user()->account->update_products && ! strtotime($productKey)) {
                    $product = Product::findProductByKey($productKey);
                    if (!$product) {
                        if (Auth::user()->can('create', ENTITY_PRODUCT)) {
                            $product = Product::createNew();
                            $product->product_key = trim($item['product_key']);
                        }
                        else{
                            $product = null;
                        }
                    }
                    if ($product && (Auth::user()->can('edit', $product))) {
                        $product->notes = ($task || $expense) ? '' : $item['notes'];
                        $product->cost = $expense ? 0 : $item['cost'];
                        $product->save();
                    }
                }
            }

            $invoiceItem = InvoiceItem::createNew();
            $invoiceItem->product_id = isset($product) ? $product->id : null;
            $invoiceItem->product_key = isset($item['product_key']) ? (trim($invoice->is_recurring ? $item['product_key'] : Utils::processVariables($item['product_key']))) : '';
            $invoiceItem->notes = trim($invoice->is_recurring ? $item['notes'] : Utils::processVariables($item['notes']));
            $invoiceItem->cost = Utils::parseFloat($item['cost']);
            $invoiceItem->qty = Utils::parseFloat($item['qty']);

            if (isset($item['custom_value1'])) {
                $invoiceItem->custom_value1 = $item['custom_value1'];
            }
            if (isset($item['custom_value2'])) {
                $invoiceItem->custom_value2 = $item['custom_value2'];
            }

            // provide backwards compatability
            if (isset($item['tax_name']) && isset($item['tax_rate'])) {
                $item['tax_name1'] = $item['tax_name'];
                $item['tax_rate1'] = $item['tax_rate'];
            }

            $invoiceItem->fill($item);

            $invoice->invoice_items()->save($invoiceItem);
        }

        return $invoice;
    }

    public function cloneInvoice($invoice, $quotePublicId = null)
    {
        $invoice->load('invitations', 'invoice_items');
        $account = $invoice->account;

        $clone = Invoice::createNew($invoice);
        $clone->balance = $invoice->amount;

        // if the invoice prefix is diff than quote prefix, use the same number for the invoice (if it's available)
        $invoiceNumber = false;
        if ($account->hasInvoicePrefix() && $account->share_counter) {
            $invoiceNumber = $invoice->invoice_number;
            if ($account->quote_number_prefix && strpos($invoiceNumber, $account->quote_number_prefix) === 0) {
                $invoiceNumber = substr($invoiceNumber, strlen($account->quote_number_prefix));
            }
            $invoiceNumber = $account->invoice_number_prefix.$invoiceNumber;
            if (Invoice::scope(false, $account->id)
                    ->withTrashed()
                    ->whereInvoiceNumber($invoiceNumber)
                    ->first()) {
                $invoiceNumber = false;
            }
        }
        $clone->invoice_number = $invoiceNumber ?: $account->getNextInvoiceNumber($clone);
        $clone->invoice_date = date_create()->format('Y-m-d');

        foreach ([
          'client_id',
          'discount',
          'is_amount_discount',
          'po_number',
          'is_recurring',
          'frequency_id',
          'start_date',
          'end_date',
          'terms',
          'invoice_footer',
          'public_notes',
          'invoice_design_id',
          'tax_name1',
          'tax_rate1',
          'tax_name2',
          'tax_rate2',
          'amount',
          'is_quote',
          'custom_value1',
          'custom_value2',
          'custom_taxes1',
          'custom_taxes2',
          'partial',
          'custom_text_value1',
          'custom_text_value2', ] as $field) {
            $clone->$field = $invoice->$field;
        }

        if ($quotePublicId) {
            $clone->is_quote = false;
            $clone->quote_id = $quotePublicId;
        }

        $clone->save();

        if ($quotePublicId) {
            $invoice->quote_invoice_id = $clone->public_id;
            $invoice->save();
        }

        foreach ($invoice->invoice_items as $item) {
            $cloneItem = InvoiceItem::createNew($invoice);

            foreach ([
                'product_id',
                'product_key',
                'notes',
                'cost',
                'qty',
                'tax_name1',
                'tax_rate1',
                'tax_name2',
                'tax_rate2',
            ] as $field) {
                $cloneItem->$field = $item->$field;
            }

            $clone->invoice_items()->save($cloneItem);
        }

        foreach ($invoice->documents as $document) {
            $cloneDocument = $document->cloneDocument();
            $invoice->documents()->save($cloneDocument);
        }

        foreach ($invoice->invitations as $invitation) {
            $cloneInvitation = Invitation::createNew($invoice);
            $cloneInvitation->contact_id = $invitation->contact_id;
            $cloneInvitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $clone->invitations()->save($cloneInvitation);
        }

        return $clone;
    }

    public function markSent($invoice)
    {
        $invoice->markInvitationsSent();
    }

    public function findInvoiceByInvitation($invitationKey)
    {
        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation) {
            return false;
        }

        $invoice = $invitation->invoice;
        if (!$invoice || $invoice->is_deleted) {
            return false;
        }

        $invoice->load('user', 'invoice_items', 'documents', 'invoice_design', 'account.country', 'client.contacts', 'client.country');
        $client = $invoice->client;

        if (!$client || $client->is_deleted) {
            return false;
        }

        return $invitation;
    }

    public function findOpenInvoices($clientId)
    {
        return Invoice::scope()
                ->whereClientId($clientId)
                ->whereIsQuote(false)
                ->whereIsRecurring(false)
                ->whereDeletedAt(null)
                ->whereHasTasks(true)
                ->where('invoice_status_id', '<', 5)
                ->select(['public_id', 'invoice_number'])
                ->get();
    }

    public function createRecurringInvoice($recurInvoice)
    {
        $recurInvoice->load('account.timezone', 'invoice_items', 'client', 'user');

        if ($recurInvoice->client->deleted_at) {
            return false;
        }

        if (!$recurInvoice->user->confirmed) {
            return false;
        }

        if (!$recurInvoice->shouldSendToday()) {
            return false;
        }

        $invoice = Invoice::createNew($recurInvoice);
        $invoice->client_id = $recurInvoice->client_id;
        $invoice->recurring_invoice_id = $recurInvoice->id;
        $invoice->invoice_number = $recurInvoice->account->getNextInvoiceNumber($invoice);
        $invoice->amount = $recurInvoice->amount;
        $invoice->balance = $recurInvoice->amount;
        $invoice->invoice_date = date_create()->format('Y-m-d');
        $invoice->discount = $recurInvoice->discount;
        $invoice->po_number = $recurInvoice->po_number;
        $invoice->public_notes = Utils::processVariables($recurInvoice->public_notes);
        $invoice->terms = Utils::processVariables($recurInvoice->terms);
        $invoice->invoice_footer = Utils::processVariables($recurInvoice->invoice_footer);
        $invoice->tax_name1 = $recurInvoice->tax_name1;
        $invoice->tax_rate1 = $recurInvoice->tax_rate1;
        $invoice->tax_name2 = $recurInvoice->tax_name2;
        $invoice->tax_rate2 = $recurInvoice->tax_rate2;
        $invoice->invoice_design_id = $recurInvoice->invoice_design_id;
        $invoice->custom_value1 = $recurInvoice->custom_value1 ?: 0;
        $invoice->custom_value2 = $recurInvoice->custom_value2 ?: 0;
        $invoice->custom_taxes1 = $recurInvoice->custom_taxes1 ?: 0;
        $invoice->custom_taxes2 = $recurInvoice->custom_taxes2 ?: 0;
        $invoice->custom_text_value1 = Utils::processVariables($recurInvoice->custom_text_value1);
        $invoice->custom_text_value2 = Utils::processVariables($recurInvoice->custom_text_value2);
        $invoice->is_amount_discount = $recurInvoice->is_amount_discount;
        $invoice->due_date = $recurInvoice->getDueDate();
        $invoice->save();

        foreach ($recurInvoice->invoice_items as $recurItem) {
            $item = InvoiceItem::createNew($recurItem);
            $item->product_id = $recurItem->product_id;
            $item->qty = $recurItem->qty;
            $item->cost = $recurItem->cost;
            $item->notes = Utils::processVariables($recurItem->notes);
            $item->product_key = Utils::processVariables($recurItem->product_key);
            $item->tax_name1 = $recurItem->tax_name1;
            $item->tax_rate1 = $recurItem->tax_rate1;
            $item->tax_name2 = $recurItem->tax_name2;
            $item->tax_rate2 = $recurItem->tax_rate2;
            $item->custom_value1 = Utils::processVariables($recurItem->custom_value1);
            $item->custom_value2 = Utils::processVariables($recurItem->custom_value2);
            $invoice->invoice_items()->save($item);
        }

        foreach ($recurInvoice->documents as $recurDocument) {
            $document = $recurDocument->cloneDocument();
            $invoice->documents()->save($document);
        }

        foreach ($recurInvoice->invitations as $recurInvitation) {
            $invitation = Invitation::createNew($recurInvitation);
            $invitation->contact_id = $recurInvitation->contact_id;
            $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $invoice->invitations()->save($invitation);
        }

        $recurInvoice->last_sent_date = date('Y-m-d');
        $recurInvoice->save();

        if ($recurInvoice->auto_bill) {
            if ($this->paymentService->autoBillInvoice($invoice)) {
                // update the invoice reference to match its actual state
                // this is to ensure a 'payment received' email is sent
                $invoice->invoice_status_id = INVOICE_STATUS_PAID;
            }
        }

        return $invoice;
    }

    public function findNeedingReminding($account)
    {
        $dates = [];

        for ($i=1; $i<=3; $i++) {
            if ($date = $account->getReminderDate($i)) {
                $field = $account->{"field_reminder{$i}"} == REMINDER_FIELD_DUE_DATE ? 'due_date' : 'invoice_date';
                $dates[] = "$field = '$date'";
            }
        }

        $sql = implode(' OR ', $dates);
        $invoices = Invoice::whereAccountId($account->id)
                    ->where('balance', '>', 0)
                    ->where('is_quote', '=', false)
                    ->where('is_recurring', '=', false)
                    ->whereRaw('('.$sql.')')
                    ->get();

        return $invoices;
    }
}

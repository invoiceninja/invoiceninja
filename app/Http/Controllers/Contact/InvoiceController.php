<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\Contact;

use App\Filters\InvoiceFilters;
use App\Http\Controllers\BaseController;
use App\Models\Invoice;
use App\Transformers\Contact\InvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class InvoiceController extends BaseController
{
    use MakesHash;

    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * List Invoices.
     *
     * @param InvoiceFilters $filters  The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function index(InvoiceFilters $filters)
    {
        $invoices = Invoice::filter($filters);

        return $this->listResponse($invoices);
    }
}

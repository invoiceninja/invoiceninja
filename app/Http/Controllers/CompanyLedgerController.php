<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */


namespace App\Http\Controllers;

use App\Http\Requests\CompanyLedger\ShowCompanyLedgerRequest;
use App\Models\CompanyLedger;
use App\Transformers\CompanyLedgerTransformer;
use Illuminate\Http\Request;

class CompanyLedgerController extends BaseController
{

    protected $entity_type = CompanyLedger::class;

    protected $entity_transformer = CompanyLedgerTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(ShowCompanyLedgerRequest $request)
    {
    	$company_ledger = CompanyLedger::whereCompanyId(auth()->user()->company()->id)->orderBy('id', 'ASC');

    	return $this->listResponse($company_ledger);
    }


}

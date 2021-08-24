<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Statements\CreateStatementRequest;

class ClientStatementController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function statement(CreateStatementRequest $request)
    {
        
    }
}
